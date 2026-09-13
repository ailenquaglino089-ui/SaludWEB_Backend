# Repositorio Backend

Capa de API y lógica de acceso a datos para el proyecto **SaludWEB** (Programación IV).

## Contenido
- controllers/
- services/
- persistence/
- core/ (Router, Response, JwtService, AuthMiddleware, Secret)
- vendor/ (firebase/php-jwt - Composer)
- db.php
- index.php
- routes.php

## Propósito
- Exponer endpoints HTTP para web y mobile.
- Mantener la lógica de negocio y persistencia separada.
- Autenticación JWT (HS256) con rutas públicas, protegidas (401) y solo-admin (403).

## Setup rápido
```bash
# Instalar dependencias (solo la primera vez)
php composer.phar install

# Verificar la API
curl http://localhost/Workspace_SaludWEB/repositorio_backend/api/health
```

## Autenticación
1. `POST /api/auth/login` con `{ "email": "...", "password": "..." }` → devuelve un JWT.
2. Enviarlo en cada petición protegida: `Authorization: Bearer <jwt>`.
3. El secreto se toma de la variable de entorno `JWT_SECRET` o se genera en `storage/secret.key`.
4. Detalles en [`../API_DOCUMENTATION.md`](../API_DOCUMENTATION.md).