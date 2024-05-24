# Blitzvideo-api

<p align="center">
    <img src="https://drive.google.com/uc?export=download&id=1yyVoEHmLQgzYpDJJJvjtpo1MHdZNP84k" width="200">
</p>

### Configuración del proyecto

-   Para comenzar, clona el repositorio de GitHub a tu máquina local. Abre una terminal y ejecuta el siguiente comando:

`Clonar con SSH:`

```
git clone git@github.com:blitzcode-company/Blitzvideo-api.git
```

`Clonar con HTTPS:`

```
git clone https://github.com/blitzcode-company/Blitzvideo-api.git
```

-   Ingresamos al proyecto `cd Blitzvideo-api` y ejecutamos:

```
composer install
```

-   Dentro del directorio del proyecto de Laravel, generamos el archivo .env con el siguiente comando:

```
cp .env.example .env
```

-   Configuramos la base de datos dentro del archivo .env:

```
DB_HOST=mysql
DB_PORT=3306
```

-   Realizamos las migraciones con el comando:

```
php artisan migrate
php db:seed
```

## Dependencia en Oauth-api

Este proyecto depende del servicio de autenticación OAuth proporcionado por el repositorio [Oauth-api](https://github.com/blitzcode-company/Oauth-api).

Asegúrate de clonar y configurar este repositorio antes de ejecutar el proyecto Blitzvideo-api.
