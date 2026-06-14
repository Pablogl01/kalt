# Ticket 01 — Auth + Perfil de Usuario

## Contexto

Fase 1 del manuscrito técnico (`KALT_Manuscrito_V2.1.md`, sección 11). La Fase 0 está cerrada: estructura, migraciones, Policies, rate limiting, sistema de diseño y entorno están operativos.

Este ticket cubre el primer módulo funcional real: que el usuario pueda registrarse, iniciar sesión y completar su perfil. Al finalizar, el usuario debe poder ver sus macros objetivo calculados (TDEE) — primer valor visible de la app.

## Objetivo

El usuario puede crear su cuenta, introducir sus datos físicos y objetivo, y ver inmediatamente los macros diarios calculados para él.

## Alcance

### 1. Autenticación (backend)

- Endpoints: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`.
- Sanctum ya configurado (Fase 0) — usar el modo SPA cookie httpOnly existente.
- FormRequest por endpoint: `RegisterRequest` (nombre, email, password, confirmación), `LoginRequest` (email, password).
- Respuestas de error en formato JSON consistente — no exponer stack traces.

### 2. Perfil de usuario (backend)

- Endpoint `PUT /api/profile` con su `UpdateProfileRequest`.
- Campos: sexo, peso (kg), altura (cm), edad, objetivo (volumen/mantenimiento/definición), nivel de actividad (sedentario/ligero/moderado/alto).
- Los campos `peso` y `grasa_corporal` usan el cast `Encrypted` (ya definido en migración de Fase 0).
- Endpoint `GET /api/profile` — devuelve perfil del usuario autenticado + macros objetivo calculados (ver punto 3).
- No exponer el `id` interno — solo el UUID.

### 3. Motor de cálculo — DietCalculator (backend)

Implementar `app/Services/DietCalculator.php` con los métodos:

- `calculateBMR(User $user): float` — Harris-Benedict según sexo, peso, altura, edad (manuscrito sección 4.1).
- `calculateTDEE(float $bmr, string $activityLevel): float` — aplicar factor de actividad (manuscrito sección 4.1).
- `calculateMacros(float $tdee, string $objetivo): array` — distribución de macros según objetivo (manuscrito sección 4.1). Devuelve `['calorias' => X, 'proteina' => X, 'carbos' => X, 'grasa' => X]` en gramos.

`DietCalculator` no accede a la base de datos — recibe datos como parámetros y devuelve resultados. Esto facilita el testing unitario.

### 4. Vistas frontend (Vue 3)

- `src/views/Auth/Login.vue` — formulario de login (email + password + botón). Manejo de errores de credenciales.
- `src/views/Auth/Register.vue` — formulario de registro (nombre, email, password, confirmación). Manejo de errores de validación.
- `src/views/Profile.vue` — formulario de perfil con los campos de la sección 2 + visualización de macros calculados al guardar.

**Visualización de macros calculados:**

Al guardar el perfil (o al cargarlo si ya existe), mostrar un bloque de 4 métricas usando los tokens semánticos de `DESIGN.md`:

- Calorías totales (`--color-calories`)
- Proteína (`--color-protein`)
- Carbohidratos (`--color-carbs`)
- Grasa (`--color-fat`)

Números grandes y bold (patrón de la referencia visual). Este bloque es la primera muestra real del sistema de diseño en producción — debe verse bien.

### 5. Store de usuario (Pinia)

- `src/stores/userStore.js` — gestionar estado de sesión (usuario autenticado / no autenticado) y datos del perfil.
- Al hacer login, cargar el perfil del usuario y guardarlo en el store.
- Al hacer logout, limpiar el store y redirigir a login.
- Proteger las rutas autenticadas con un guard de Vue Router que consulte el store.

## Fuera de alcance

- Onboarding wizard (Fase 3).
- Configuración de comidas y días de entreno (Fase 3).
- Restricciones alimentarias (Fase 3).
- Cualquier lógica de plan semanal.

## Tests

### Pest (ejecuta la IA)

**Unitarios (`tests/Unit/Services/DietCalculatorTest.php`):**
- BMR correcto para hombre con valores conocidos (ej: 80kg, 180cm, 30 años → resultado esperado exacto).
- BMR correcto para mujer con valores conocidos.
- TDEE correcto con cada uno de los 4 factores de actividad.
- Macros correctos para objetivo "volumen" (30/50/20%).
- Macros correctos para objetivo "mantenimiento" (30/45/25%).
- Macros correctos para objetivo "definición" (40/35/25%).
- La suma de calorías de los macros iguala el TDEE (tolerancia ±1 kcal por redondeo).
- Caso edge: peso < 40kg no rompe el cálculo.
- Caso edge: peso > 200kg no rompe el cálculo.

**Feature (`tests/Feature/`):**
- `POST /api/auth/register` con datos válidos devuelve HTTP 201.
- `POST /api/auth/register` con email ya existente devuelve HTTP 422.
- `POST /api/auth/login` con credenciales correctas devuelve HTTP 200 y establece cookie.
- `POST /api/auth/login` con credenciales incorrectas devuelve HTTP 422.
- `GET /api/profile` sin autenticación devuelve HTTP 401.
- `PUT /api/profile` actualiza el perfil y devuelve macros calculados.
- `GET /api/profile` de otro usuario devuelve HTTP 403 (Policy).

### Tests manuales (ejecuta el programador)

1. Registrarse con un email nuevo → verificar redirección correcta tras registro.
2. Intentar registrarse con el mismo email → verificar mensaje de error visible en el formulario.
3. Hacer login con credenciales correctas → verificar que el perfil se carga en el store.
4. Hacer login con credenciales incorrectas → verificar mensaje de error.
5. Completar el perfil con datos reales (ej: hombre, 80kg, 180cm, 30 años, moderado, volumen) → verificar que los macros calculados aparecen en pantalla y son coherentes (aprox. 2.900-3.100 kcal).
6. Recargar la página tras login → verificar que la sesión persiste (cookie httpOnly funciona).
7. Hacer logout → verificar que redirige a login y el store queda limpio.
8. Intentar acceder a `/profile` sin sesión → verificar redirección a login.
9. Verificar en DevTools que **no existe** ningún token en localStorage ni sessionStorage.
10. Verificar en DevTools → Network que la tipografía Plus Jakarta Sans se carga correctamente.

## Criterios de aceptación

- [x] Los tres endpoints de auth funcionan correctamente con sus FormRequests.
- [x] `DietCalculator` implementado con los 3 métodos, sin acceso a BD.
- [x] Tests unitarios de `DietCalculatorTest` pasan al 100%.
- [x] Tests de feature de auth y perfil pasan al 100%.
- [x] `GET /api/profile` incluye los macros calculados en la respuesta.
- [x] Vista de perfil muestra bloque de macros con tokens de color correctos y tipografía Plus Jakarta Sans.
- [x] Store de usuario gestiona sesión correctamente.
- [x] Rutas autenticadas protegidas con guard de Vue Router.
- [x] Tests manuales 1-10 superados.

## Notas

- `DietCalculator` es el Service más crítico del proyecto — sus tests deben estar al 100% antes de cerrar este ticket, no después.
- El bloque de macros en `Profile.vue` es la primera UI real del sistema de diseño: si algo no encaja visualmente (colores, tipografía, tamaño de números), mejor detectarlo aquí que en Fase 3 cuando haya más vistas.
- Este ticket activa el **análisis profundo** del paso 5 del flujo (toca auth + datos de salud cifrados).
