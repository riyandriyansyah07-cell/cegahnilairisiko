<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Cegah Sebelum Terlambat') | Peringatan Dini Akademik</title>
    <meta name="description" content="Sistem peringatan dini akademik untuk mahasiswa FTII UHAMKA. Ketahui risiko nilaimu sebelum nilai akhir terbit.">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.dataset.theme = t;
            } catch (e) { document.documentElement.dataset.theme = 'light'; }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh">
    @yield('body')
</body>
</html>
