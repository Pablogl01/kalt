# Ticket 00 — Fundación del proyecto KALT

## Contexto

KALT (anteriormente "FitDiet") es una app web de nutrición deportiva: genera planes de dieta personalizados de forma determinista (sin IA en V1), permite seguimiento diario de comidas/entrenos, lista de la compra automática y estadísticas de progreso.

Stack: Laravel 11 + PostgreSQL 16 + Redis 7 (backend) · Vue 3 + Vite + Tailwind CSS v4 + Pinia (frontend) · Docker.

Este ticket corresponde a la **Fase 0 — Fundación** del manuscrito técnico V2. Es la base sobre la que se construyen todas las fases posteriores; no debe empezarse Fase 1 sin que esto esté cerrado.

> El sistema de diseño descrito en la sección 9 del manuscrito original queda **sustituido** por el definido en `DESIGN.md` (paleta cálida crema/naranja/negro, navegación responsive sidebar+bottom bar). Este ticket usa `DESIGN.md` como referencia de diseño.

## Objetivo

Dejar el proyecto preparado a nivel de estructura, seguridad y configuración base, de forma que las siguientes fases (motor de cálculo, plan semanal, seguimiento, etc.) se construyan sobre cimientos correctos desde el principio.

## Alcance

### 1. Estructura de carpetas inicial

- Backend Laravel: crear estructura de `app/Http/Controllers`, `app/Http/Requests`, `app/Policies`, `app/Jobs`, `app/Services`, `app/Models` según el manuscrito (sección 5.1), aunque vacías o con stubs.
- Frontend Vue: crear estructura de `src/views`, `src/components`, `src/stores` según el manuscrito (sección 5.2), con componentes vacíos/placeholder.
- Añadir store de navegación (Pinia) compartido entre sidebar (desktop) y bottom bar (mobile), según `DESIGN.md` sección 1.

### 2. Migraciones con índices desde el inicio

- Crear las migraciones de todas las tablas descritas en el manuscrito (secciones 3.1–3.5).
- Todas las claves primarias expuestas en API como UUID (`$table->uuid('id')->primary()`).
- Incluir desde ya los índices definidos en la sección 3.6 del manuscrito (no se añaden después).
- Campos sensibles de salud (peso, grasa corporal, alergias) preparados para usar el cast `Encrypted` de Laravel.

### 3. Autenticación y autorización base

- Configurar Laravel Sanctum en modo SPA (cookie httpOnly), sin tokens en localStorage/sessionStorage.
- Crear Policies vacías/iniciales: `WeeklyPlanPolicy`, `DailyLogPolicy`, `ShoppingListPolicy`, `MealLogPolicy`, todas implementando el patrón `view(User $user, $resource) => $user->id === $resource->user_id`.
- Configurar rate limiting en `RouteServiceProvider`:
  - Login: 10 intentos/minuto/IP.
  - API autenticada: 60 requests/minuto/usuario.
  - Generación de plan: 3/hora/usuario.

### 4. Modelos base

- Definir `$fillable` explícito en todos los modelos Eloquent (nunca `$guarded = []`).
- No crear lógica de negocio todavía, solo modelos + relaciones.

### 5. Sistema de diseño (tailwind.config.js)

Implementar los tokens definidos en `DESIGN.md`:

- Paleta base: `--color-bg` (crema `#FDF6F0`), `--color-surface` (blanco), `--color-surface-dark` (`#1F1B16`), `--color-text`, `--color-text-muted`, `--color-accent` (verde pastel `#A8E063`), `--color-accent-dark` (`#7CC242`).
- Tokens semánticos de macros (sin cambios): `--color-protein`, `--color-carbs`, `--color-fat`, `--color-calories`, `--color-goal`, `--color-success-adherence`, `--color-partial-adherence`.
- **Regla crítica:** el naranja (`--color-carbs`) es de uso exclusivo para carbohidratos. No usarlo en botones, CTAs, estados activos de navegación ni ningún otro elemento. El acento general de la UI es `--color-accent` (verde pastel).
- Componentes base de navegación: sidebar (desktop, fondo `--color-surface-dark`) y bottom tab bar (mobile), ambos placeholder en este ticket, conectados al store de navegación.
- Tipografía: pendiente de confirmación (Inter o Plus Jakarta Sans) — bloquea el cierre de este punto.

### 6. Entorno y herramientas

- Docker: levantar contenedores de Laravel, PostgreSQL 16, Redis 7 y Vite/Vue.
- Configurar Laravel Debugbar en local (detección de N+1).
- Configurar Pest como framework de testing.

## Fuera de alcance (no tocar en este ticket)

- Cualquier lógica del motor de cálculo (DietCalculator, MealDistributor, etc.) — Fase 1.
- Generación de plan semanal, Jobs, polling — Fase 2.
- Cualquier vista funcional más allá de placeholders.
- Seeder de alimentos (Fase 1).

## Criterios de aceptación

- [ ] Estructura de carpetas backend y frontend creada según manuscrito.
- [ ] Store de navegación compartido creado y conectado a sidebar/bottom bar placeholder.
- [ ] Todas las migraciones de las secciones 3.1–3.5 creadas, con índices de la sección 3.6 incluidos.
- [ ] Sanctum configurado en modo SPA cookie, verificable con un endpoint de prueba autenticado.
- [ ] Las 4 Policies creadas y registradas, con test de feature que confirme HTTP 403 al acceder a un recurso ajeno (puede ser con modelos de prueba/dummy).
- [ ] Rate limiting configurado y verificable (test o prueba manual con curl/Postman).
- [ ] `tailwind.config.js` con paleta base + 7 tokens de macros, según `DESIGN.md`.
- [ ] Sidebar (desktop) y bottom bar (mobile) placeholder funcionando en ambos breakpoints, sin solapamientos visuales.
- [ ] Docker levanta los 4 servicios (Laravel, Postgres, Redis, Vite) sin errores.
- [ ] Pest configurado y ejecuta correctamente un test trivial.

## Notas

- Este ticket toca autenticación, autorización y datos de salud cifrados → corresponde **análisis profundo** de seguridad en el paso 5 del flujo (no el check ligero por defecto).
- Tipografía pendiente: si no se confirma antes de implementar, usar una sans-serif del sistema como placeholder documentado (`TODO: tipografía pendiente`) y no bloquear el resto del ticket por esto.
- El nombre "KALT" sustituye a "FitDiet" en el manuscrito; revisar si conviene actualizar el documento o solo usarlo de cara afuera (naming en código vs naming de producto).
