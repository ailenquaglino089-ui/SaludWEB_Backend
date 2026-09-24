# Repositorio Backend

Capa de API y lógica de acceso a datos para el proyecto **SaludWEB** (Programación IV).

## Contenido
- controllers/
- services/
- persistence/
- core/ (Router, Response, JwtService, AuthMiddleware, Config, Secret)
- vendor/ (firebase/php-jwt - instalado con Composer)
- db.php
- index.php
- routes.php

## Propósito
- Exponer endpoints HTTP para web y mobile.
- Mantener la lógica de negocio y persistencia separada.
- Autenticación JWT (HS256) con rutas públicas, protegidas (401) y solo-admin (403).

## Puesta en marcha

### Requisitos
- PHP 8.x
- Composer
- MySQL corriendo (en XAMPP: iniciar el módulo `MySQL` desde el Control Panel)

### 1. Clonar el repositorio
```bash
git clone https://github.com/ailenquaglino089-ui/SaludWEB_Backend.git
cd SaludWEB_Backend
```

### 2. Instalar dependencias
```bash
composer install
```

Si Composer no está instalado globalmente, descargá `composer.phar` dentro de la carpeta del proyecto y ejecutá:

```bash
php composer.phar install
```

### 3. Configurar entorno (opcional)
Copia `.env.example` a `.env` y ajustá los valores según corresponda (base de datos, `JWT_SECRET`, `CORS_ORIGINS`). En desarrollo, si no se configura nada, se usan los valores por defecto de `db.php` y `core/Secret.php`.

### 4. Correr el backend

**Opción A: servidor de desarrollo de PHP**
```bash
php -S localhost:8000 index.php
```

**Opción B: Apache/XAMPP**
Copiá la carpeta dentro de `htdocs` (ej: `C:\xampp\htdocs\SaludWEB_Backend`) y abrí la URL correspondiente:
```
http://localhost/SaludWEB_Backend/api/health
```

### 5. Verificar
```bash
curl http://localhost:8000/api/health
```
Debe responder `200` con `{"data":{"status":"ok",...},"message":"API SaludWEB - Programación IV"}`.

> En el primer request, `db.php` crea automáticamente la base de datos `pacientes`, las tablas y los datos de ejemplo (no hace falta importar ningún SQL).

## Autenticación
1. `POST /api/auth/login` con `{ "email": "...", "password": "..." }` → devuelve un JWT.
2. Enviarlo en cada petición protegida: `Authorization: Bearer <jwt>`.
3. El secreto se toma de la variable de entorno `JWT_SECRET` o se genera en `storage/secret.key`.

## SSO (Google / Microsoft Entra ID)
4. `POST /api/auth/sso` con `{ "provider": "google" | "microsoft", "id_token": "..." }` → verifica
   la firma del `id_token` contra las claves públicas del proveedor (JWKS) y devuelve el JWT de
   SaludWEB (misma respuesta que `/api/auth/login`). Solo habilita cuentas locales existentes y activas
   (el email del proveedor debe coincidir); no se auto-crean cuentas.
5. Configurá las variables de entorno en `.env`: `SSO_GOOGLE_CLIENT_ID`, `SSO_MICROSOFT_CLIENT_ID`
   y `SSO_MICROSOFT_TENANT` (ver `.env.example`). Sin credenciales, el endpoint responde `501`.
6. El `id_token` lo obtiene la app móvil `SaludWEB_Mobile` con `expo-auth-session` (flujo público/PKCE,
   sin secret en el dispositivo); los botones de cada proveedor solo se muestran si está configurado.