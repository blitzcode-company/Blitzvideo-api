<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BlitzVideo API</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Styles -->
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #ffffff;
            color: #001f31;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
        }
        .logo {
            height: 150px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 2.5rem;
            color: #0eaafd;
        }
        .subtitle {
            font-size: 1.5rem;
            color: #001f31;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="{{ asset('images/Blitzvideo.png') }}" alt="BlitzVideo Logo" class="logo">
        <div class="title">Bienvenido a BlitzVideo API</div>
        <div class="subtitle">La API está corriendo correctamente</div>
    </div>
</body>
</html>
