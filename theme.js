// set the theme before first paint: saved choice, else the system theme
(function () {
    var theme;
    try { theme = localStorage.getItem('theme'); } catch (e) {}
    if ( ! theme) theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', theme);
})();
