<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asunto }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: rgb(230, 230, 230);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #173b57;
        }

        p {
            line-height: 1.6;
            color: rgb(113, 128, 150);
        }

        .button-container {
            text-align: center;
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
            text-align: center;
        }

        .secondary-link {
            font-size: 14px;
            color: rgb(113, 128, 150);
            line-height: 1.5;
            margin-top: 10px;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }

        .secondary-link a {
            color: #0baafe;
            text-decoration: none;
        }

        .secondary-link a:hover {
            color: #0077cc;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <p align="center">
            <img src="https://lh3.googleusercontent.com/pw/AP1GczPTSuQ4hwjzLK1tjehAK1OmEoDEGwZCA4SmwAvHBh3uwA6Yu-4Vlg9lgEX-kMaYEcZvU2Fj5qPeGjAAXMFdas1JZA7opLNi7KTF6c3JDjtY88LQ9AzuqqsqI-8AsLOixsQ9v0WPfwEsShRELzD8FSpI=w709-h804-s-no?authuser=0"
                width="200">
        </p>
        <h2>Hola, {{$name}}:</h2>
        <p>{!! nl2br(e($mensaje)) !!}</p>
        <div class="button-container">
            <a href="{{ $link }}" class="button">Haz clic aquí</a>
        </div>
        <br>
        <hr>
        <br>
        <p class="secondary-link">
            Si tiene problemas para hacer clic en el botón "Restablecer contraseña", copie y pegue la siguiente URL en
            su navegador web:
            <a href="{{ $link }}">{{ $link }}</a>
        </p>
        <div class="footer">
            <p>Este es un mensaje generado automáticamente. No respondas a este correo.</p>
        </div>
    </div>
    
</body>

</html>
