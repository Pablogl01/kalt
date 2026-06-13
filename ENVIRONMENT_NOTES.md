# ENVIRONMENT_NOTES.md — Notas de entorno KALT

Registro de problemas de entorno descubiertos durante el desarrollo. Se actualiza en cada retrospectiva.

---

## Entorno del programador

| Componente | Versión | Estado |
|---|---|---|
| OS | Windows (PowerShell) | ✅ |
| PHP (local) | 8.3.31 | ⚠️ Extensiones faltantes (ver abajo) |
| Composer (local) | — | ❌ No disponible |
| Node.js (local) | v22.21.0 | ✅ |
| npm (local) | 11.6.2 | ✅ |
| Docker | 29.5.3 | ✅ Instalado |
| Docker Desktop | — | ⚠️ Debe estar arrancado para usar Docker |

---

## Problemas conocidos

### 1. PowerShell no soporta `&&` como separador de comandos

**Síntoma:** `comando1 && comando2` devuelve error de parser.

**Fix:** Usar `;` en su lugar, o comandos separados:
```powershell
# ❌ Falla en PowerShell
npm install && npm run dev

# ✅ Correcto
npm install; npm run dev
# O en comandos separados
```

### 2. Docker Desktop debe estar arrancado antes de usar `docker` o `docker compose`

**Síntoma:** `ERROR: failed to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine`

**Fix:** Arrancar Docker Desktop manualmente antes de ejecutar cualquier comando Docker.

Verificar que está activo:
```powershell
docker info
```

### 3. PHP local tiene extensiones faltantes

**Síntoma:** Al ejecutar `php --version` localmente aparecen warnings de curl, fileinfo, mbstring, openssl, pdo_pgsql, pgsql, sodium, zip.

**Impacto:** PHP local no puede ejecutar Laravel ni Composer correctamente.

**Fix:** Todo el código PHP se ejecuta dentro del container Docker (`kalt_app`). Usar `docker compose exec app php artisan ...` en lugar de `php artisan ...` directamente.

### 4. Composer no está instalado localmente

**Fix:** Ejecutar Composer dentro del container:
```powershell
docker compose exec app composer install
docker compose exec app composer require paquete/nombre
```

---

## Comandos de referencia para este entorno

### Levantar el entorno (primera vez)
```powershell
# 1. Arrancar Docker Desktop primero
# 2. Copiar .env
Copy-Item backend\.env.example backend\.env
# 3. Levantar contenedores
docker compose up -d
# 4. Instalar dependencias PHP
docker compose exec app composer install
# 5. Generar APP_KEY
docker compose exec app php artisan key:generate
# 6. Ejecutar migraciones
docker compose exec app php artisan migrate
# 7. Frontend ya corre via el container vite en :5173
```

### Comandos artisan
```powershell
docker compose exec app php artisan [comando]
```

### Ejecutar tests Pest
```powershell
docker compose exec app php artisan test
# O directamente con pest:
docker compose exec app ./vendor/bin/pest
```

### Logs
```powershell
docker compose logs app
docker compose logs -f app   # follow
```

---

*Actualizado: Ticket 00 — Fundación*
