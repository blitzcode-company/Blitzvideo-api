<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asunto }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
        }

        p {
            line-height: 1.6;
            color: #555;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            margin-top: 20px;
            background-color: #0baafe;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .button:hover {
            background-color: #0077cc;
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>

<body>
    <div class="container">
        <p align="center">
            <img src="https://lh3.googleusercontent.com/pw/AP1GczPTSuQ4hwjzLK1tjehAK1OmEoDEGwZCA4SmwAvHBh3uwA6Yu-4Vlg9lgEX-kMaYEcZvU2Fj5qPeGjAAXMFdas1JZA7opLNi7KTF6c3JDjtY88LQ9AzuqqsqI-8AsLOixsQ9v0WPfwEsShRELzD8FSpI=w709-h804-s-no?authuser=0" width="200">
        </p>
        <h1>{{ $asunto }}</h1>
        <p>{!! nl2br(e($mensaje)) !!}</p>
        <a href="{{ $link }}" class="button">Haz clic aquí</a>
        <div class="footer">
            <p>Este es un mensaje generado automáticamente. No respondas a este correo.</p>
        </div>
    </div>
</body>

</html>
