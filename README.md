# KALT - Nutricion Deportiva

KALT es una aplicacion web de nutricion deportiva disenada para generar planes de dieta personalizados de forma determinista, realizar seguimiento diario de comidas y peso, automatizar listas de la compra y ofrecer analisis estadisticos.

## Stack Tecnologico

- Backend: Laravel 11 (PHP 8.3) + PostgreSQL 16 + Redis 7
- Frontend: Vue 3 (Vite) + Tailwind CSS v4 + Pinia
- Entorno: Docker (Nginx, PHP-FPM, PostgreSQL, Redis, Node/Vite)

## Instalacion y Arranque del Entorno

Para levantar el entorno completo con Docker por primera vez, ejecuta en tu terminal:

1. Clonar el repositorio:
   ```bash
   git clone <url-del-repositorio> kalt
   cd kalt
   ```

2. Levantar contenedores con Docker Compose:
   (Asegurate de que Docker Desktop esta iniciado)
   ```bash
   docker compose up -d
   ```

3. Instalar dependencias de PHP (Composer):
   ```bash
   docker compose exec app composer install
   ```

4. Copiar y configurar variables de entorno en el Backend:
   - En Windows (PowerShell):
     ```powershell
     Copy-Item backend\.env.example backend\.env
     ```
   - En Linux / macOS:
     ```bash
     cp backend/.env.example backend/.env
     ```

5. Generar la clave de la aplicacion Laravel:
   ```bash
   docker compose exec app php artisan key:generate
   ```

6. Ejecutar migraciones de la base de datos:
   ```bash
   docker compose exec app php artisan migrate
   ```

## Acceso Local

Una vez levantado todo el entorno, los servicios estaran disponibles en las siguientes rutas:

- Frontend (Vue): http://localhost:5173 (con proxy a Nginx integrado)
- Backend API (Laravel): http://localhost:8000

---

## Comandos Utiles de Desarrollo

### Ejecucion de Tests (Pest)
Para ejecutar la suite de pruebas unitarias y de integración del backend:
```bash
docker compose exec app ./vendor/bin/pest
```

### Detener los Contenedores
```bash
docker compose down
```

### Reiniciar el Servidor Vite
Si cambias configuraciones del frontend (como vite.config.js):
```bash
docker compose restart vite
```
