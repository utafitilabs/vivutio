<?php

declare(strict_types=1);

/*
 * This file is part of the vivutio core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vivutio\Bundle\IdentityBundle\Controller;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\LinkPurposeEnum;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Exception\EmailAlreadyUsedException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidDepartmentException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPasswordException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPersonException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPlaceException;
use Vivutio\Bundle\IdentityBundle\Exception\LastSuperAdminException;
use Vivutio\Bundle\IdentityBundle\Model\TierChange;
use Vivutio\Bundle\IdentityBundle\Repository\AccountLinkRepository;
use Vivutio\Bundle\IdentityBundle\Repository\DepartmentRepository;
use Vivutio\Bundle\IdentityBundle\Repository\PositionRepository;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Bundle\IdentityBundle\Service\AccountLinkService;
use Vivutio\Bundle\IdentityBundle\Service\MailAvailability;
use Vivutio\Bundle\IdentityBundle\Service\PasswordRulesService;
use Vivutio\Bundle\IdentityBundle\Service\PlaceDirectoryService;
use Vivutio\Bundle\IdentityBundle\Service\UserService;

/**
 * Configuring a person, as designed in vivutio-designs/team/member-configure.html:
 * the details, signing in and the position, each a form saved by itself, and
 * deactivating or bringing back.
 *
 * Every route names a tier-only pair (as ruled in uhifadhi, #67, so no
 * position can raise itself or take over a colleague's account) and the
 * account rule for this person (nobody acts above their own tier).
 */
final readonly class PersonController
{
    public const string CONFIGURE = 'identity_person_configure';
    public const string DETAILS = 'identity_person_details';
    public const string SIGN_IN = 'identity_person_sign_in';
    public const string POSITION = 'identity_person_position';
    public const string DEACTIVATE = 'identity_person_deactivate';
    public const string REACTIVATE = 'identity_person_reactivate';
    public const string SEND_RESET = 'identity_person_send_reset';
    public const string ADD = 'identity_person_add';
    public const string CREATE = 'identity_person_create';
    public const string INVITE = 'identity_person_invite';
    public const string SEND_INVITATION = 'identity_person_send_invitation';

    public const string MANAGE = 'directory.manage';
    public const string MANAGE_PERSONAL_DETAILS = 'personal_details.manage';

    private const string SAVED = 'person.saved';

    public function __construct(
        private Environment $twig,
        private UserService $accounts,
        private PositionRepository $positions,
        private AuthorizationCheckerInterface $authorization,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
        private AccountLinkService $links,
        private AccountLinkRepository $sentLinks,
        private MailAvailability $mail,
        private PasswordRulesService $rules,
        private DepartmentRepository $departments,
        private PlaceDirectoryService $places,
    ) {
    }

    /**
     * Adding somebody, ported from uhifadhi's team/invite: two ways in, a
     * first password handed over in the room, or an invitation by email.
     */
    #[Route('/team/add', name: self::ADD, methods: ['GET'])]
    #[IsGranted(self::MANAGE)]
    public function add(): Response
    {
        return $this->addPage();
    }

    #[Route('/team/add/create', name: self::CREATE, methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    public function create(Request $request): Response
    {
        $payload = $request->getPayload();
        $typed = [];
        foreach (['first_name', 'last_name', 'email', 'position'] as $field) {
            $typed[$field] = $payload->getString($field);
        }

        if (!$this->tokenIsValid('person_add', $request)) {
            return $this->addPage(created: $typed, expired: true);
        }

        $position = $this->positionNamed($typed['position']);
        if (false === $position) {
            return $this->addPage(created: $typed, wrong: ['position' => 'Choose one of the positions offered.']);
        }

        try {
            $probe = (new User())->setFirstName(trim($typed['first_name']))->setLastName(trim($typed['last_name']))->setEmail(mb_strtolower(trim($typed['email'])));
            $this->rules->refuseIfBroken($probe, $payload->getString('password'), $payload->getString('password'));
            $person = $this->accounts->create($typed['email'], $typed['first_name'], $typed['last_name'], $payload->getString('password'));
            $this->accounts->changeDetails($person, $typed['first_name'], $typed['last_name'], null);
            $this->accounts->changePosition($person, $position);
        } catch (InvalidPersonException $refusal) {
            return $this->addPage(created: $typed, wrong: [$refusal->field => $refusal->getMessage()]);
        } catch (InvalidPasswordException $refusal) {
            return $this->addPage(created: $typed, wrong: ['password' => $refusal->getMessage()]);
        } catch (EmailAlreadyUsedException) {
            return $this->addPage(created: $typed, wrong: ['email' => 'Somebody already signs in with that address.']);
        }

        return new RedirectResponse($this->urls->generate(TeamController::MEMBER, ['uuid' => $person->getUuid()]));
    }

    #[Route('/team/add/invite', name: self::INVITE, methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    public function invite(
        Request $request,
        #[CurrentUser]
        ?User $sender,
    ): Response {
        $payload = $request->getPayload();
        $typed = ['email' => $payload->getString('email'), 'position' => $payload->getString('position')];

        if (!$this->tokenIsValid('person_invite', $request)) {
            return $this->addPage(invited: $typed, expired: true);
        }
        if (!$this->mail->isAvailable()) {
            return $this->addPage(invited: $typed, wrong: ['invite_email' => 'This installation cannot send email yet.']);
        }

        $position = $this->positionNamed($typed['position']);
        if (false === $position) {
            return $this->addPage(invited: $typed, wrong: ['invite_position' => 'Choose one of the positions offered.']);
        }

        try {
            $person = $this->accounts->invite($typed['email'], $position, $sender);
        } catch (InvalidPersonException $refusal) {
            return $this->addPage(invited: $typed, wrong: ['invite_email' => $refusal->getMessage()]);
        } catch (EmailAlreadyUsedException) {
            return $this->addPage(invited: $typed, wrong: ['invite_email' => 'Somebody already signs in with that address.']);
        }

        $this->links->sendInvitation($person, $sender);

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(self::SAVED, \sprintf('An invitation was sent to %s. It works once, for a week.', $person->getEmail()));
        }

        return new RedirectResponse($this->urls->generate(TeamController::MEMBER, ['uuid' => $person->getUuid()]));
    }

    #[Route('/team/{uuid}/configure', name: self::CONFIGURE, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(self::MANAGE)]
    #[IsGranted(AccountVoter::ACT_ON, subject: 'person')]
    public function configure(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
    ): Response {
        $session = $request->getSession();
        $saved = $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag()->get(self::SAVED) : [];

        return $this->form($person, saved: \is_string($saved[0] ?? null) ? $saved[0] : null);
    }

    #[Route('/team/{uuid}/configure/details', name: self::DETAILS, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    #[IsGranted(AccountVoter::ACT_ON, subject: 'person')]
    public function details(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
    ): Response {
        $payload = $request->getPayload();
        $typed = ['first_name' => $payload->getString('first_name'), 'last_name' => $payload->getString('last_name'), 'phone' => $payload->getString('phone')];

        if (!$this->tokenIsValid('person_details', $request)) {
            return $this->form($person, typed: $typed, expired: true);
        }

        try {
            $this->accounts->changeDetails($person, $typed['first_name'], $typed['last_name'], $typed['phone']);
        } catch (InvalidPersonException $refusal) {
            return $this->form($person, typed: $typed, wrong: [$refusal->field => $refusal->getMessage()]);
        }

        return $this->savedTo($request, $person, 'The details are saved.');
    }

    #[Route('/team/{uuid}/configure/sign-in', name: self::SIGN_IN, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::MANAGE_PERSONAL_DETAILS)]
    #[IsGranted(AccountVoter::ACT_ON, subject: 'person')]
    public function signIn(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
    ): Response {
        $payload = $request->getPayload();
        $typed = ['email' => $payload->getString('email', (string) $person->getEmail()), 'tier' => $payload->getString('tier', $person->getTier()->value)];

        if (!$this->tokenIsValid('person_sign_in', $request)) {
            return $this->form($person, typed: $typed, expired: true);
        }

        $tier = TierEnum::tryFrom($typed['tier']);
        if (null === $tier) {
            return $this->form($person, typed: $typed, wrong: ['tier' => 'Choose one of the tiers offered.']);
        }

        if ($tier !== $person->getTier()) {
            if ($this->accounts->isLastActiveSuperAdmin($person)) {
                return $this->form($person, typed: $typed, wrong: ['tier' => \sprintf('%s is the only active Super Admin, so their tier stays. Make somebody else a Super Admin first.', $person->getFullName())]);
            }
            if (!$this->authorization->isGranted(AccountVoter::CHANGE_TIER, new TierChange($person, $tier))) {
                return $this->form($person, typed: $typed, wrong: ['tier' => \sprintf('You cannot give the tier %s.', $tier->label())]);
            }
        }

        try {
            $this->accounts->changeEmail($person, $typed['email']);
            if ($tier !== $person->getTier()) {
                $this->accounts->changeTier($person, $tier);
            }
        } catch (InvalidPersonException $refusal) {
            return $this->form($person, typed: $typed, wrong: [$refusal->field => $refusal->getMessage()]);
        } catch (EmailAlreadyUsedException) {
            return $this->form($person, typed: $typed, wrong: ['email' => 'Somebody already signs in with that address.']);
        } catch (LastSuperAdminException $refusal) {
            return $this->form($person, typed: $typed, wrong: ['tier' => $refusal->getMessage()]);
        }

        return $this->savedTo($request, $person, 'Signing in is saved.');
    }

    #[Route('/team/{uuid}/configure/position', name: self::POSITION, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    #[IsGranted(AccountVoter::ACT_ON, subject: 'person')]
    public function position(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
    ): Response {
        $payload = $request->getPayload();
        $chosen = $payload->getString('position');
        $typedDepartment = $payload->getString('department');
        $typedSupports = array_values(array_filter($payload->all('supports'), \is_string(...)));
        $typedPlace = $payload->getString('posted_at');
        $typed = ['position' => $chosen, 'department' => $typedDepartment, 'posted_at' => $typedPlace];

        if (!$this->tokenIsValid('person_position', $request)) {
            return $this->form($person, typed: $typed, expired: true);
        }

        // Where they are posted now may be kept even once it is no longer offered.
        $current = null === $person->getPostedKind() ? '' : $person->getPostedKind().':'.$person->getPostedId();
        try {
            $place = $typedPlace === $current ? $this->places->postingOf($person) : $this->places->typed($typedPlace, 'posted_at');
        } catch (InvalidPlaceException $refusal) {
            return $this->form($person, typed: $typed, wrong: [$refusal->field => $refusal->getMessage()]);
        }

        $department = '' === $typedDepartment ? null : $this->departmentNamed($typedDepartment);
        if (false === $department) {
            return $this->form($person, typed: $typed, wrong: ['department' => 'Choose one of the departments offered.']);
        }
        $supports = [];
        foreach ($typedSupports as $supported) {
            $found = $this->departmentNamed($supported);
            if (false === $found) {
                return $this->form($person, typed: $typed, wrong: ['department' => 'Choose among the departments offered.']);
            }
            $supports[] = $found;
        }

        $position = null;
        if ('' !== $chosen) {
            // Looked up only when it is a uuid at all: the database refuses
            // anything else with an error, not an empty answer.
            $position = 1 === preg_match('{^'.Requirement::UUID.'$}D', $chosen) ? $this->positions->findOneBy(['uuid' => $chosen]) : null;
            if (null === $position) {
                return $this->form($person, typed: $typed, wrong: ['position' => 'Choose one of the positions offered.']);
            }
        }

        try {
            $this->accounts->changeSeat($person, $place, $department, $supports, $position);
        } catch (InvalidDepartmentException|InvalidPlaceException $refusal) {
            return $this->form($person, typed: $typed, wrong: [$refusal->field => $refusal->getMessage()]);
        }

        return $this->savedTo($request, $person, 'The position is saved.');
    }

    #[Route('/team/{uuid}/deactivate', name: self::DEACTIVATE, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    #[IsGranted(AccountVoter::DEACTIVATE, subject: 'person')]
    public function deactivate(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
    ): Response {
        if (!$this->tokenIsValid('person_account', $request)) {
            return $this->form($person, expired: true);
        }

        try {
            $this->accounts->deactivate($person);
        } catch (LastSuperAdminException $refusal) {
            return $this->form($person, wrong: ['account' => $refusal->getMessage()]);
        }

        return new RedirectResponse($this->urls->generate(TeamController::MEMBER, ['uuid' => $person->getUuid()]));
    }

    #[Route('/team/{uuid}/reactivate', name: self::REACTIVATE, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    #[IsGranted(AccountVoter::ACT_ON, subject: 'person')]
    public function reactivate(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
    ): Response {
        if (!$this->tokenIsValid('person_account', $request)) {
            return $this->form($person, expired: true);
        }

        $this->accounts->reactivate($person);

        return new RedirectResponse($this->urls->generate(TeamController::MEMBER, ['uuid' => $person->getUuid()]));
    }

    /**
     * A link to set a new password, mailed to the person and sent by whoever
     * configures them, as uhifadhi's ruled sign-in help (D).
     */
    #[Route('/team/{uuid}/send-reset', name: self::SEND_RESET, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    #[IsGranted(AccountVoter::ACT_ON, subject: 'person')]
    public function sendReset(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
        #[CurrentUser]
        ?User $sender,
    ): Response {
        if (!$this->tokenIsValid('person_account', $request)) {
            return $this->form($person, expired: true);
        }
        // Somebody invited sets their password by accepting, with their name.
        if (!$this->mail->isAvailable() || !$person->isActive() || !$person->isVerified()) {
            return $this->form($person, wrong: ['account' => 'No link can be sent to this account now.']);
        }

        $this->links->sendReset($person, $sender);

        return $this->savedTo($request, $person, \sprintf('A link was sent to %s. It works once and expires in an hour.', $person->getEmail()));
    }

    /** The invitation again, replacing the one sent before. */
    #[Route('/team/{uuid}/send-invitation', name: self::SEND_INVITATION, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    #[IsGranted(AccountVoter::ACT_ON, subject: 'person')]
    public function sendInvitation(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
        #[CurrentUser]
        ?User $sender,
    ): Response {
        if (!$this->tokenIsValid('person_account', $request)) {
            return $this->form($person, expired: true);
        }
        if (!$this->mail->isAvailable() || $person->isVerified()) {
            return $this->form($person, wrong: ['account' => 'No invitation can be sent to this account now.']);
        }

        $this->links->sendInvitation($person, $sender);

        return $this->savedTo($request, $person, \sprintf('An invitation was sent to %s. It works once, for a week.', $person->getEmail()));
    }

    /**
     * @param array<string, string> $typed what was sent, shown back in place of what is stored
     * @param array<string, string> $wrong a refusal, keyed by the field it is about
     */
    private function form(User $person, array $typed = [], array $wrong = [], bool $expired = false, ?string $saved = null): Response
    {
        $tiers = array_values(array_filter(
            TierEnum::cases(),
            fn (TierEnum $tier): bool => $tier === $person->getTier() || $this->authorization->isGranted(AccountVoter::CHANGE_TIER, new TierChange($person, $tier)),
        ));

        $values = [
            'first_name' => (string) $person->getFirstName(),
            'last_name' => (string) $person->getLastName(),
            'phone' => (string) $person->getPhone(),
            'email' => (string) $person->getEmail(),
            'tier' => $person->getTier()->value,
            'position' => (string) $person->getPosition()?->getUuid(),
            'department' => (string) $person->getDepartment()?->getUuid(),
            'posted_at' => null === $person->getPostedKind() ? '' : $person->getPostedKind().':'.$person->getPostedId(),
        ];

        return new Response($this->twig->render('@Identity/team/configure.html.twig', [
            'person' => $person,
            'values' => [...$values, ...$typed],
            'wrong' => $wrong,
            'expired' => $expired,
            'saved' => $saved,
            'tiers' => $tiers,
            'positions' => $this->positions->findBy([], ['name' => 'ASC']),
            'departments' => $this->departments->findBy([], ['name' => 'ASC']),
            'places' => $this->places->groups(),
            'last_super_admin' => $this->accounts->isLastActiveSuperAdmin($person),
            'may_deactivate' => $person->isActive() && $this->authorization->isGranted(AccountVoter::DEACTIVATE, $person),
            'mail_available' => $this->mail->isAvailable(),
            'last_link' => $this->sentLinks->findOneBy(['account' => $person, 'purpose' => $person->isVerified() ? LinkPurposeEnum::Reset : LinkPurposeEnum::Invitation], ['createdAt' => 'DESC']),
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param array<string, string> $created what was typed into creating somebody
     * @param array<string, string> $invited what was typed into inviting somebody
     * @param array<string, string> $wrong   a refusal, keyed by the field it is about
     */
    private function addPage(array $created = [], array $invited = [], array $wrong = [], bool $expired = false): Response
    {
        return new Response($this->twig->render('@Identity/team/add.html.twig', [
            'created' => $created,
            'invited' => $invited,
            'wrong' => $wrong,
            'expired' => $expired,
            'positions' => $this->positions->findBy([], ['name' => 'ASC']),
            'mail_available' => $this->mail->isAvailable(),
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** The department a form names by its identifier, false for one that is not offered. */
    private function departmentNamed(string $chosen): Department|false
    {
        return (1 === preg_match('{^'.Requirement::UUID.'$}D', $chosen) ? $this->departments->findOneBy(['uuid' => $chosen]) : null) ?? false;
    }

    /** The position a form names by its identifier, null for none, false for one that is not offered. */
    private function positionNamed(string $chosen): Position|false|null
    {
        if ('' === $chosen) {
            return null;
        }

        return (1 === preg_match('{^'.Requirement::UUID.'$}D', $chosen) ? $this->positions->findOneBy(['uuid' => $chosen]) : null) ?? false;
    }

    private function tokenIsValid(string $id, Request $request): bool
    {
        return $this->tokens->isTokenValid(new CsrfToken($id, $request->getPayload()->getString('_token')));
    }

    private function savedTo(Request $request, User $person, string $message): RedirectResponse
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(self::SAVED, $message);
        }

        return new RedirectResponse($this->urls->generate(self::CONFIGURE, ['uuid' => $person->getUuid()]));
    }
}
