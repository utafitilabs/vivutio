/* vivutio. Ported from the design project, vivutio-designs/vivutio.js. */
/* What every page does: remember dark or light, and whether the menu is folded.
   Loaded in <head> so nothing flashes. */
(function () {
    var root = document.documentElement;
    var stored = null;
    try { stored = localStorage.getItem("vivutio-theme"); } catch (e) {}
    var dark = "#dark" === location.hash || ("#light" !== location.hash && "dark" === stored);
    root.classList.toggle("light", !dark);
    try { if ("folded" === localStorage.getItem("vivutio-menu")) { root.classList.add("folded"); } } catch (e) {}

    window.toggleTheme = function () {
        var light = root.classList.toggle("light");
        try { localStorage.setItem("vivutio-theme", light ? "light" : "dark"); } catch (e) {}
    };
    window.foldMenu = function () {
        var folded = root.classList.toggle("folded");
        try { localStorage.setItem("vivutio-menu", folded ? "folded" : "full"); } catch (e) {}
    };
    /* One of a set: options and switches. */
    document.addEventListener("click", function (e) {
        var b = e.target.closest(".option, .switch button");
        if (!b || b.disabled) { return; }
        [].slice.call(b.parentNode.children).forEach(function (o) { o.setAttribute("aria-pressed", o === b ? "true" : "false"); });
        b.dispatchEvent(new CustomEvent("chosen", {bubbles: true}));
    });
}());
