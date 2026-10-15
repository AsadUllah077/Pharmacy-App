<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <script>
        (() => {
            let preference = 'system';
            try { preference = localStorage.getItem('pharmaflow.theme') || 'system'; } catch {}
            const dark = preference === 'dark' || (preference !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>
    <title inertia>PharmaFlow</title>
    @vite('resources/js/app.ts')
    @inertiaHead
</head>
<body>@inertia</body>
</html>
