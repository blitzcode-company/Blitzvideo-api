# Blitzvideo-api

<p align="center">
    <img src="https://drive.google.com/uc?export=download&id=1yyVoEHmLQgzYpDJJJvjtpo1MHdZNP84k" width="200">
</p>

### Configuración del proyecto

-   Para comenzar, clona el repositorio de GitHub a tu máquina local. Abre una terminal y ejecuta el siguiente comando:

`Vía SSH:`

```
git clone git@github.com:blitzcode-company/Blitzvideo-api.git
```

`Vía HTTPS:`

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

- Generar la clave de la aplicación

```
php artisan key:generate
```

-   Realizamos las migraciones con el comando:

```
php artisan migrate
php db:seed
```

## Dependencia en Oauth-api

Este proyecto depende del servicio de autenticación OAuth proporcionado por el repositorio [Oauth-api](https://github.com/blitzcode-company/Oauth-api).

## Docker Compose

Inicia el proyecto con el siguiente comando:

```
sudo docker-compose up -d
```
El proyecto estará corriendo en el puerto **8001**. Puede corroborarlo ingresando a `http://localhost:8001/`. 

**Nota:** Luego debes iniciar el proyecto de Oauth-api.

## Pasos para configurar MinIO y el proyecto

- **Inicio de sesión en MinIO:**
Accede a la interfaz de administración de MinIO en http://localhost:9001 utilizando las credenciales predeterminadas:

    `Usuario: admin`
    
    `Contraseña: Blitzcode123.`

 - **Creación del bucket:**
Crea un nuevo bucket llamado `blitzvideo-bucket` desde la interfaz de **MinIO**.

 - **Creación del usuario:**
    Crea un nuevo usuario llamado `blitzcode-admin` con la contraseña `Blitzcode123`.

 - **Configuración de las políticas:**
        Marca todas las políticas disponibles para el usuario `blitzcode-admin`.

 - **Generación de Access Key y Secret Key:**
        Accede al usuario blitzcode-admin y crea una cuenta de servicio.
        Genera las `Access Key` y `Secret Key` para esta cuenta de servicio.

 - **Configuración en el proyecto:**
        Copia las Access Key y Secret Key generadas y pégalas en el archivo **.env** del proyecto bajo las variables `AWS_ACCESS_KEY_ID` y `AWS_SECRET_ACCESS_KEY`.

 - **Configuración del bucket como público:**
    - Accede al bucket **blitzvideo-bucket**.
    - Ve a **Summary**-> **Access Policy**.
    - Configura la política de acceso como **public**.