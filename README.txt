# KALT — Nutrición Deportiva

KALT es una aplicación web de nutrición deportiva diseñada para generar planes de dieta personalizados de forma determinista, realizar seguimiento diario de comidas y peso, automatizar listas de la compra y ofrecer análisis estadísticos.

## Stack Tecnológico

- **Backend:** Laravel 11 (PHP 8.3) + PostgreSQL 16 + Redis 7
- **Frontend:** Vue 3 (Vite) + Tailwind CSS v4 + Pinia
- **Entorno:** Docker (Nginx, PHP-FPM, PostgreSQL, Redis, Node/Vite)

## Instalación y Arranque del Entorno

Para levantar el entorno completo con Docker por primera vez, ejecuta en tu terminal:

1. **Clonar el repositorio:**
   ```bash
   git clone <url-del-repositorio> kalt
   cd kalt
   ```

2. **Levantar contenedores con Docker Compose:**
   *(Asegúrate de que Docker Desktop está iniciado)*
   ```bash
   docker compose up -d
   ```

3. **Instalar dependencias de PHP (Composer):**
   ```bash
   docker compose exec app composer install
   ```

4. **Copiar y configurar variables de entorno en el Backend:**
   ```bash
   # En Windows (PowerShell)
   Copy-Item backend\.env.example backend\.env
   
   # O en Linux / macOS
   cp backend/.env.example backend/.env
   ```

5. **Generar la clave de la aplicación Laravel:**
   ```bash
   docker compose exec app php artisan key:generate
   ```

6. **Ejecutar migraciones de la base de datos:**
   ```bash
   docker compose exec app php artisan migrate
   ```

## Acceso Local

Una vez levantado todo el entorno, los servicios estarán disponibles en las siguientes rutas:

- **Frontend (Vue):** [http://localhost:5173](http://localhost:5173) (con proxy a Nginx integrado)
- **Backend API (Laravel):** [http://localhost:8000](http://localhost:8000)

---

## Comandos Útiles de Desarrollo

### Ejecución de Tests (Pest)
Para ejecutar la suite de pruebas unitarias y de integración del backend:
```bash
docker compose exec app ./vendor/bin/pest
```

### Detener los Contenedores
```bash
docker compose down
```

### Reiniciar el Servidor Vite
Si cambias configuraciones del frontend (como `vite.config.js`):
```bash
docker compose restart vite
```
