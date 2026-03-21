<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Atlas Sandbox') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
