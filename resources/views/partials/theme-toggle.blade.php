{{--
    Light/dark preference, applied before first paint from localStorage so a
    dark-mode machine never flashes the light theme on load.
--}}
<script>
    (function () {
        var root = document.documentElement;
        function apply(t) { root.classList.toggle('dark', t === 'dark'); }
        try {
            var s = localStorage.getItem('pk-theme');
            apply(s === 'dark' || (s !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches));
        } catch (e) {}
    })();
</script>
