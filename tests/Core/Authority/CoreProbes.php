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

namespace Vivutio\Core\Tests\Core\Authority;

use Vivutio\Bundle\IdentityBundle\Controller\DepartmentController;
use Vivutio\Bundle\IdentityBundle\Controller\InvitationController;
use Vivutio\Bundle\IdentityBundle\Controller\PasswordController;
use Vivutio\Bundle\IdentityBundle\Controller\PersonController;
use Vivutio\Bundle\IdentityBundle\Controller\PositionController;
use Vivutio\Bundle\IdentityBundle\Controller\SecurityController;
use Vivutio\Bundle\IdentityBundle\Controller\SettingsController;
use Vivutio\Bundle\IdentityBundle\Controller\TeamController;
use Vivutio\Bundle\IdentityBundle\Test\AuthorityTestCase;
use Vivutio\Bundle\IdentityBundle\Test\Probe;
use Vivutio\Bundle\ShellBundle\Controller\DashboardController;

/**
 * A probe for every route of the core, and of this application.
 */
final class CoreProbes
{
    public const string POSITION_UUID = '0199a6f0-5ea7-7e10-8000-0000000005ea';

    private const string CANARY = '/team/'.AuthorityTestCase::CANARY_UUID;

    private const string POSITION = '/team/positions/'.self::POSITION_UUID;

    private const string DEPARTMENT = '/team/departments/'.AuthorityTestCase::DEPARTMENT_UUID;

    /**
     * @return list<Probe>
     */
    public static function all(): array
    {
        return [
            new Probe(SecurityController::SIGN_IN, 'GET', '/login'),
            new Probe(SecurityController::SIGN_OUT, 'GET', '/logout'),
            new Probe(PasswordController::FORGOT, 'GET', '/login/forgot'),
            // The canary's address: a stranger may ask, and nothing tells them it exists.
            new Probe(PasswordController::FORGOT, 'POST', '/login/forgot', ['email' => 'canary.c4f7e1@vivutio-camps.example'], formAt: '/login/forgot'),
            new Probe(PasswordController::LINK, 'GET', '/login/reset/'.str_repeat('0', 24).'/'.str_repeat('A', 43)),
            new Probe(PasswordController::RESET, 'GET', '/login/reset'),
            new Probe(PasswordController::RESET, 'POST', '/login/reset', ['password' => 'a stranger\'s password', 'repeat' => 'a stranger\'s password'], formAt: '/login/reset'),
            new Probe(InvitationController::LINK, 'GET', '/login/join/'.str_repeat('0', 24).'/'.str_repeat('A', 43)),
            new Probe(InvitationController::ACCEPT, 'GET', '/login/join'),
            new Probe(InvitationController::ACCEPT, 'POST', '/login/join', ['first_name' => 'A', 'last_name' => 'Stranger', 'password' => 'a stranger\'s password', 'repeat' => 'a stranger\'s password'], formAt: '/login/join'),
            new Probe(DashboardController::HOME, 'GET', '/'),
            new Probe(DashboardController::MINE, 'GET', '/me'),
            new Probe(TeamController::TEAM, 'GET', '/team'),
            new Probe(TeamController::MEMBER, 'GET', '/team/'.AuthorityTestCase::CANARY_UUID),
            new Probe(PersonController::CONFIGURE, 'GET', self::CANARY.'/configure'),
            new Probe(PersonController::DETAILS, 'POST', self::CANARY.'/configure/details', ['first_name' => 'Taken', 'last_name' => 'Over'], formAt: self::CANARY.'/configure'),
            // The canary's own address and tier, so a write that is allowed leaves the canary in place.
            new Probe(PersonController::SIGN_IN, 'POST', self::CANARY.'/configure/sign-in', ['email' => 'canary.c4f7e1@vivutio-camps.example', 'tier' => 'super_admin'], formAt: self::CANARY.'/configure'),
            new Probe(PersonController::POSITION, 'POST', self::CANARY.'/configure/position', ['position' => ''], formAt: self::CANARY.'/configure'),
            new Probe(PersonController::DEACTIVATE, 'POST', self::CANARY.'/deactivate', formAt: self::CANARY.'/configure'),
            new Probe(PersonController::REACTIVATE, 'POST', self::CANARY.'/reactivate', formAt: self::CANARY.'/configure'),
            new Probe(PersonController::SEND_RESET, 'POST', self::CANARY.'/send-reset', formAt: self::CANARY.'/configure'),
            new Probe(PersonController::SEND_INVITATION, 'POST', self::CANARY.'/send-invitation', formAt: self::CANARY.'/configure'),
            new Probe(PersonController::ADD, 'GET', '/team/add'),
            // Sent by each kind of person in turn, so the second allowed finds the address taken.
            new Probe(PersonController::CREATE, 'POST', '/team/add/create', ['first_name' => 'Added', 'last_name' => 'By a probe', 'email' => 'added.by.a.probe@vivutio-camps.example', 'password' => 'a first passphrase', 'position' => ''], formAt: '/team/add'),
            new Probe(PersonController::INVITE, 'POST', '/team/add/invite', ['email' => 'invited.by.a.probe@vivutio-camps.example', 'position' => ''], formAt: '/team/add'),
            new Probe(DepartmentController::REGISTER, 'GET', '/team/departments'),
            new Probe(DepartmentController::ADD, 'POST', '/team/departments', ['name' => 'Added by a probe'], formAt: '/team/departments'),
            new Probe(DepartmentController::SHOW, 'GET', self::DEPARTMENT),
            new Probe(DepartmentController::CONFIGURE, 'GET', self::DEPARTMENT.'/configure'),
            // Allowing a module's pair to the department every Staff probe belongs to: from anybody below the tiers, an escalation.
            new Probe(DepartmentController::CONFIGURE, 'POST', self::DEPARTMENT.'/configure', ['name' => AuthorityTestCase::DEPARTMENT_NAME, 'head' => '', 'allows' => ['notes.read']], formAt: self::DEPARTMENT.'/configure'),
            new Probe(PositionController::REGISTER, 'GET', '/team/positions'),
            new Probe(PositionController::ADD, 'POST', '/team/positions', ['name' => 'Added by a probe'], formAt: '/team/positions'),
            new Probe(PositionController::SHOW, 'GET', self::POSITION),
            new Probe(PositionController::CONFIGURE, 'GET', self::POSITION.'/configure'),
            // What it already grants, so a write that is allowed leaves it as it was.
            new Probe(PositionController::CONFIGURE, 'POST', self::POSITION.'/configure', ['name' => 'Probed seat', 'grants' => ['directory.read']], formAt: self::POSITION.'/configure'),
            new Probe(SettingsController::ORGANIZATION, 'GET', '/settings/organization'),
            new Probe(SettingsController::CONFIGURE_ORGANIZATION, 'GET', '/settings/configure/organization'),
            new Probe(SettingsController::CONFIGURE_ORGANIZATION, 'POST', '/settings/configure/organization', ['name' => 'Taken over'], formAt: '/settings/configure/organization'),
        ];
    }
}
