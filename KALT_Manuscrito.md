# KALT — Manuscrito Técnico

**Versión:** 2.1 — V1 MVP (actualización de naming y sistema de diseño)
**Autor:** Pablo García
**Stack:** Laravel 11 · Vue 3 · Vite · PostgreSQL · Redis

> Esta v2.1 actualiza el naming del proyecto (FitDiet → KALT) y sustituye el sistema de diseño de la sección 9 por el definido en `DESIGN.md` (paleta cálida crema/naranja/negro, navegación responsive sidebar+bottom bar). El resto del contenido respecto a V2.0 se mantiene sin cambios funcionales.

---

## 1. Visión del Producto

KALT es una aplicación web de nutrición deportiva que actúa como un nutricionista personal automatizado. Su objetivo es que cualquier usuario pueda llevar un seguimiento fácil e intuitivo de su dieta, adaptada a su objetivo deportivo (definición, mantenimiento o volumen), con la misma calidad y personalización que ofrecería un profesional.

### 1.1 Objetivos principales

- Generar planes de dieta personalizados basados en el perfil y objetivo del usuario.
- Permitir el seguimiento diario de comidas y macronutrientes de forma visual e intuitiva.
- Adaptar la dieta automáticamente ante imprevistos: comidas saltadas, entrenos extra o días de descanso inesperados.
- Ofrecer una lista de la compra automática con opciones de sustitución inteligente.
- Mostrar estadísticas de progreso para que el usuario visualice su evolución.

### 1.2 Principios de diseño

- Simplicidad ante todo: el usuario no debe necesitar conocimientos de nutrición.
- Flexibilidad real: la app se adapta al usuario, no al revés.
- Sin dependencia de IA externa en V1: toda la lógica es determinista y controlada.
- Datos precisos: cada cálculo tiene base nutricional contrastada.
- Seguridad por defecto: autorización estricta, datos sensibles protegidos desde el día 1.
- Feedback claro al usuario: cada acción del sistema que modifique la dieta debe explicarse al usuario.

---

## 2. Módulos del Sistema

### 2.1 Perfil de usuario

El perfil recoge toda la información necesaria para calcular la dieta y personalizarla.

**Datos personales y físicos**
- Nombre, edad, sexo, peso (kg), altura (cm)
- Porcentaje de grasa corporal (opcional, mejora la precisión del cálculo)
- Objetivo activo: definición / mantenimiento / volumen
- Nivel de actividad general: sedentario / ligero / moderado / alto

**Configuración de entrenos**
- Días de la semana planificados para entrenar
- Tipo de sesión por día (fuerza / cardio / mixto) — opcional en V1
- Hora habitual del gimnasio por día (configurable por separado para cada día de la semana)

**Restricciones alimentarias**

El usuario puede indicar alimentos que debe evitar, con tres niveles de restricción:

| Tipo | Comportamiento en la app |
| --- | --- |
| Alergia | Hard block. Nunca aparece en el plan ni en sustitutos. No puede ser forzado por el usuario. |
| Intolerancia | No aparece por defecto. El usuario puede forzarlo puntualmente si lo desea. |
| No me gusta | No aparece en el plan generado. Puede aparecer como sustituto de último recurso si no hay alternativas. |

**Validación de restricciones en el backend**

Toda restricción alimentaria se valida en el servidor, nunca solo en el frontend. Cuando el usuario selecciona un sustituto, el backend verifica que el alimento elegido esté en `food_substitutes` del original Y que no conste en `user_food_restrictions` del usuario. No se confía en la selección del cliente — esto previene que una alergia sea omitida mediante manipulación de la petición.

**Configuración de comidas**
- Número de comidas al día (mínimo 2, máximo 7)
- La app sugiere el número óptimo según el objetivo (ver sección 4.2)
- El usuario puede personalizar nombre y hora de cada comida
- La app detecta automáticamente qué comidas quedan cerca del horario de gimnasio y las marca como pre-entreno o post-entreno

### 2.2 Onboarding progresivo

El proceso de registro se divide en tres pasos diferenciados para reducir la fricción inicial y mostrar valor cuanto antes:

| Paso | Datos recogidos | Resultado inmediato |
| --- | --- | --- |
| Paso 1 — Mínimo vital | Sexo, peso, altura, edad, objetivo | Se muestran los macros objetivo calculados (TDEE) al instante |
| Paso 2 — Personalización | Nivel de actividad, días de entreno, número de comidas | Se genera el primer plan semanal |
| Paso 3 — Restricciones | Alergias, intolerancias, alimentos no deseados | El plan se regenera respetando las restricciones |

Los pasos 2 y 3 pueden completarse después del registro inicial. El usuario puede ver un plan funcional tras el Paso 1.

### 2.3 Plan de dieta semanal

El plan semanal se genera automáticamente a partir del perfil del usuario mediante el motor de cálculo (sección 4). La generación se realiza de forma asíncrona (sección 4.4). El usuario puede regenerar el plan en cualquier momento o ajustarlo manualmente.

- Vista semanal con todos los días y sus comidas
- Cada comida muestra alimentos, cantidades en gramos y macros
- El usuario puede sustituir cualquier alimento desde la propia vista del plan mediante el componente unificado `SubstituteSelector` (sección 5.2)
- Los cambios en el plan actualizan automáticamente la lista de la compra

### 2.4 Seguimiento diario

Es el módulo de uso más frecuente. El usuario lo consulta y actualiza a lo largo del día.

**Registro de comidas**
- Marcar cada comida como realizada o saltada
- Ver barra de progreso de macros en tiempo real (proteína / carbohidratos / grasa / calorías) usando los tokens de color semánticos del sistema de diseño (`DESIGN.md`)
- Añadir una comida extra no planificada con sus alimentos y cantidades
- Al añadir una comida extra, el sistema recalcula los macros disponibles para las comidas restantes y muestra un mensaje explicativo (sección 4.6)

**Registro de entrenamiento**

Cuatro escenarios posibles:

| Situación | Acción del usuario | Respuesta de la app |
| --- | --- | --- |
| Día de entreno planificado → ha entrenado | Sin acción (estado por defecto) | Sin cambios en la dieta |
| Día de entreno planificado → no ha entrenado | Marca "No he entrenado" | Reduce calorías y redistribuye macros en comidas restantes. Muestra mensaje explicativo. |
| Día de descanso → ha entrenado | Marca "He entrenado hoy" | Aumenta calorías, añade carbos pre/post entreno. Muestra mensaje explicativo. |
| Día de descanso → no ha entrenado | Sin acción (estado por defecto) | Sin cambios en la dieta |

**Feedback de recálculo obligatorio**

Cada vez que el sistema modifique cantidades de comidas (por comida saltada, entrenamiento imprevisto o comida extra), debe mostrarse un mensaje contextual no intrusivo al usuario. Ejemplo: "Hemos ajustado tus comidas restantes porque añadiste 350 kcal extra a las 15:00." El usuario nunca debe ver cambiar los gramos de un alimento sin entender por qué.

### 2.5 Lista de la compra

- Generada automáticamente a partir del plan semanal activo
- Agrupada por categorías: proteína, carbohidratos, verduras, frutas, lácteos, grasas, otros
- El usuario puede marcar los alimentos que ya tiene en casa
- Si un alimento no le gusta o no lo quiere comprar, puede marcarlo y la app sugiere 2-3 sustitutos compatibles usando `SubstituteSelector`
- La lista se actualiza automáticamente si el plan cambia durante la semana

### 2.6 Estadísticas y progreso

Panel de gráficas para visualizar la evolución del usuario.

**Bloque corporal**
- Evolución del peso — gráfica de línea temporal
- Registro manual de peso (no obligatorio diario), con nota opcional ("en ayunas", "después de entreno")

**Bloque de adherencia a la dieta**
- Porcentaje de días con dieta completada por semana y por mes
- Calorías reales vs objetivo por día — gráfica de barras (Chart.js)
- Macros medios reales vs objetivo — gráfica radar (Chart.js)

**Bloque de entrenamiento**
- Días entrenados vs planificados por semana
- Racha actual de días entrenados consecutivos
- Días de entreno extra realizados

**Bloque combinado**
- Heatmap de adherencia completa (dieta + entreno) — componente SVG personalizado en `AdherenceHeatmap.vue` (sección 4.7), pensado para mostrarse sobre tarjeta de contraste oscuro (`--color-surface-dark`) según `DESIGN.md`
- Mejor racha histórica de adherencia completa

---

## 3. Modelo de Datos

Base de datos PostgreSQL. Todos los identificadores expuestos en la API son UUIDs (no integers secuenciales) para evitar enumeración y ataques IDOR. Los IDs internos (claves foráneas no expuestas) mantienen integer para rendimiento.

**Política de IDs:** todas las tablas usan UUID como clave primaria expuesta en la API (`$table->uuid('id')->primary()`). Las relaciones foráneas internas pueden usar `bigInteger` para mantener el rendimiento de los joins.

### 3.1 Tablas de usuario

| Tabla | Descripción | Campos clave |
| --- | --- | --- |
| `users` | Datos personales y físicos | id (UUID), nombre, email, sexo, peso*, altura, edad, grasa_corporal*, objetivo, nivel_actividad |
| `user_food_restrictions` | Alimentos restringidos por el usuario | id, user_id, food_id, tipo (alergia/intolerancia/no_me_gusta)* |
| `user_weight_logs` | Registro histórico de peso | id, user_id, fecha, peso*, nota |
| `meal_templates` | Configuración de comidas del usuario | id, user_id, num_comidas, es_personalizado |
| `meal_slots` | Cada hueco de comida del usuario | id, meal_template_id, orden, nombre, hora_objetivo, es_pre_entreno, es_post_entreno |

**Columnas sensibles con cifrado:** los campos marcados con `*` (peso, grasa_corporal en `users`; peso en `user_weight_logs`; tipo en `user_food_restrictions`) almacenan datos de salud sujetos a RGPD/LOPD. Se cifran a nivel de aplicación usando el cast `Encrypted` de Laravel (requiere `APP_KEY` robusto).

### 3.2 Tablas de alimentos

| Tabla | Descripción | Campos clave |
| --- | --- | --- |
| `foods` | Base de datos de alimentos con macros por 100g | id, nombre, categoria, calorias, proteina, carbos, grasa, apto_volumen, apto_definicion, apto_mantenimiento |
| `food_substitutes` | Relaciones de sustitución entre alimentos | id, food_id, substitute_food_id, similitud_macros |

### 3.3 Tablas de planificación

| Tabla | Descripción | Campos clave |
| --- | --- | --- |
| `weekly_plans` | Plan semanal generado | id (UUID), user_id, semana_inicio, generado_en, status (pending/ready/failed) |
| `day_plans` | Plan de un día concreto | id, weekly_plan_id, fecha, calorias_objetivo, proteina_obj, carbos_obj, grasa_obj |
| `meals` | Comida planificada dentro del día | id, day_plan_id, meal_slot_id, nombre, hora_objetivo |
| `meal_items` | Alimento y cantidad dentro de una comida planificada | id, meal_id, food_id, cantidad_gramos, calorias, proteina, carbos, grasa |

**Campo `status` en `weekly_plans`:** necesario para soportar la generación asíncrona mediante Laravel Queues. El frontend consulta este campo periódicamente (polling cada 2s) hasta recibir `ready`. En caso de `failed`, se muestra un mensaje de error con opción de reintentar.

### 3.4 Tablas de seguimiento

| Tabla | Descripción | Campos clave |
| --- | --- | --- |
| `daily_logs` | Registro real del día del usuario | id, user_id, fecha, entreno_planificado, ha_entrenado, hora_gimnasio, tipo_sesion, recalculo_motivo |
| `meal_logs` | Registro de cada comida (hecha, saltada o extra) | id, daily_log_id, meal_id, es_extra, realizada, hora_real |
| `meal_log_items` | Alimentos reales de una comida registrada | id, meal_log_id, food_id, cantidad_gramos, calorias, proteina, carbos, grasa |

### 3.5 Tablas de compra

| Tabla | Descripción | Campos clave |
| --- | --- | --- |
| `shopping_lists` | Lista de la compra semanal | id, weekly_plan_id, generada_en |
| `shopping_items` | Ítem de la lista con su estado | id, shopping_list_id, food_id, cantidad_total, categoria, tengo_en_casa, no_lo_quiero, sustituido_por_food_id |

### 3.6 Índices de base de datos requeridos

Deben declararse en las migraciones iniciales. No añadirlos posteriormente implica un coste mucho mayor con datos reales:

| Tabla | Índice | Justificación |
| --- | --- | --- |
| `daily_logs` | INDEX (user_id, fecha) | Consulta principal del DailyLog — ejecutada múltiples veces por día |
| `meal_logs` | INDEX (daily_log_id) | Join frecuente desde daily_logs |
| `meal_log_items` | INDEX (meal_log_id) | Join de carga del seguimiento diario |
| `meal_items` | INDEX (meal_id) | Join de carga del plan semanal |
| `user_food_restrictions` | INDEX (user_id) | Consultado en cada generación de plan y cada sustitución |
| `weekly_plans` | INDEX (user_id, semana_inicio) | Localización rápida del plan activo del usuario |
| `food_substitutes` | INDEX (food_id) | Consultado en cada operación de sustitución |

---

## 4. Motor de Cálculo (sin IA)

Todo el cálculo de la V1 es determinista y no depende de ninguna API externa. La lógica está implementada en el backend Laravel en `app/Services/`.

### 4.1 Cálculo de TDEE y macros objetivo

**Paso 1 — BMR (Harris-Benedict)**

- Hombre: `88.36 + (13.4 × peso) + (4.8 × altura) − (5.7 × edad)`
- Mujer: `447.6 + (9.2 × peso) + (3.1 × altura) − (4.3 × edad)`

**Paso 2 — TDEE según nivel de actividad**

| Nivel de actividad | Factor | Descripción |
| --- | --- | --- |
| Sedentario | × 1.2 | Sin ejercicio o muy poco |
| Ligero | × 1.375 | 1-2 días de entreno por semana |
| Moderado | × 1.55 | 3-5 días de entreno por semana |
| Alto | × 1.725 | 6-7 días de entreno por semana |

**Paso 3 — Ajuste por objetivo**

| Objetivo | Ajuste calórico | Distribución de macros |
| --- | --- | --- |
| Volumen | TDEE + 300 kcal | 30% proteína / 50% carbohidratos / 20% grasa |
| Mantenimiento | TDEE | 30% proteína / 45% carbohidratos / 25% grasa |
| Definición | TDEE − 300 kcal | 40% proteína / 35% carbohidratos / 25% grasa |

### 4.2 Distribución de comidas

| Objetivo | Comidas sugeridas | Justificación |
| --- | --- | --- |
| Volumen | 5-6 | Alto volumen calórico, difícil de concentrar en pocas tomas |
| Mantenimiento | 4-5 | Distribución estándar equilibrada |
| Definición | 3-4 | Favorece el control del hambre con tomas más saciantes |

### 4.3 Distribución de macros por tipo de comida

| Tipo de comida | Proteína | Carbohidratos | Grasa |
| --- | --- | --- | --- |
| Pre-entreno | 35% | 40% | 25% |
| Post-entreno | 45% | 35% | 20% |
| Resto del día | Distribución estándar del objetivo | | |

### 4.4 Motor de generación de dieta — ejecución asíncrona

La generación del plan semanal se ejecuta como un Job de Laravel Queues, no de forma síncrona en el request HTTP. Esto evita timeouts y garantiza feedback inmediato mientras el plan se procesa en segundo plano.

| Paso | Descripción |
| --- | --- |
| 1. Request HTTP | El controller recibe la petición, crea el registro `weekly_plans` con `status='pending'` y despacha `GenerateDietJob::dispatch($weeklyPlan)`. Responde inmediatamente con HTTP 202 Accepted y el UUID del plan. |
| 2. Generación en Job | El Job ejecuta el algoritmo: calcula macros por MealSlot, selecciona alimentos de la BD por categoría con tolerancia ±10%, excluye restricciones del usuario, calcula gramos exactos y crea DayPlan + Meals + MealItems. |
| 3. Estado ready | Al finalizar, el Job actualiza `status='ready'`. Si falla, `status='failed'` con mensaje de error. |
| 4. Polling del frontend | Vue consulta `GET /api/plans/{uuid}/status` cada 2 segundos hasta recibir `status='ready'`. Entonces carga el plan. Si `status='failed'`, muestra error con botón de reintento. |

**Tolerancia del motor de macros:** la tolerancia de ±10% se mantiene como valor inicial. Se revisará tras las primeras semanas de uso con datos reales. Si el algoritmo no encuentra combinación válida con ±10%, amplía automáticamente a ±15% antes de marcar el Job como `failed`.

### 4.5 Motor de sustituciones

- Buscar en `food_substitutes` los sustitutos predefinidos del alimento
- Filtrar los que el usuario no tiene restringidos (validación backend, no solo UI)
- Ordenar por similitud de macros
- Devolver las 3 mejores opciones al usuario mediante `SubstituteSelector` (componente unificado)
- Al elegir sustituto, recalcular la cantidad en gramos para mantener los macros de la comida

### 4.6 Recálculo por eventos del día

En todos los casos de recálculo, el sistema debe persistir en `daily_logs` un campo `recalculo_motivo` con el evento que disparó el cambio. Este campo se usa para generar el mensaje explicativo al usuario.

**Caso A: comida saltada**
- Detectar los macros no consumidos de la comida saltada
- Calcular cuántas comidas quedan por realizar en el día
- Redistribuir los macros sobrantes proporcionalmente entre las comidas restantes
- Actualizar los MealItems de esas comidas con las nuevas cantidades
- Mostrar al usuario: "Tu comida de las [hora] fue saltada. Hemos redistribuido [X] kcal entre tus comidas restantes."

**Caso B: no ha entrenado (día planificado)**
- Calcular la reducción calórica aplicable (200-400 kcal según perfil)
- Reducir principalmente carbohidratos de las comidas restantes
- Actualizar el DayPlan con los nuevos macros
- Mostrar al usuario: "Como no has entrenado hoy, hemos reducido [X] kcal de tus comidas restantes."

**Caso C: ha entrenado (día de descanso)**
- Calcular el aumento calórico aplicable
- Pedir hora de gimnasio si no está indicada
- Añadir carbohidratos en la comida pre-entreno más cercana y proteína en la post-entreno
- Si no quedan comidas, registrar el exceso como información para el día siguiente
- Mostrar al usuario: "Hemos añadido [X] kcal a tus comidas de hoy por el entreno no planificado."

**Caso D: comida extra añadida**
- El usuario registra los alimentos y cantidades de la comida extra
- El sistema calcula los macros de esa comida
- Resta esos macros de los disponibles para el resto del día
- Redistribuye proporcionalmente entre las comidas aún no realizadas
- Si los macros del día ya están al 100%, avisa al usuario
- Mostrar al usuario: "Hemos ajustado tus comidas restantes porque añadiste [X] kcal extra a las [hora]."

### 4.7 Implementación del heatmap de adherencia

El heatmap de adherencia (estilo GitHub) no se implementa con Chart.js por carecer de tipo `matrix` nativo. Se construye como componente SVG personalizado en `AdherenceHeatmap.vue`:

- Generar una cuadrícula de celdas 7 × N semanas desde la fecha de registro del usuario
- Para cada día: color verde si adherencia completa (dieta + entreno), verde claro si parcial, gris si sin datos
- Tooltip al hover con resumen del día (calorías consumidas vs objetivo, entrenó sí/no)
- Leyenda de colores con escala de 4 niveles consistente con los tokens de `DESIGN.md`

---

## 5. Stack Técnico

| Capa | Tecnología | Justificación |
| --- | --- | --- |
| Frontend | Vue 3 + Vite | SPA reactiva, Vite ya en uso en proyectos propios |
| Estilos | Tailwind CSS | Design tokens definidos en `tailwind.config.js` (ver `DESIGN.md`) |
| Gráficas | Chart.js + Vue-ChartJS | Ligero, cubre barras, línea y radar. El heatmap se implementa en SVG personalizado |
| Backend | Laravel 11 | API robusta |
| Base de datos | PostgreSQL | Ya en uso en proyectos anteriores |
| Caché / Colas | Redis | Driver de caché para DayPlan (TTL 5-15 min) y driver de colas para GenerateDietJob |
| Autenticación | Laravel Sanctum (cookie mode) | Configurado en modo SPA con httpOnly cookies — evita exposición del token en localStorage |
| Testing | Pest | Tests unitarios obligatorios para DietCalculator, MealDistributor y DayRecalculator desde Fase 1 |
| Build/Deploy | Docker | Entornos consistentes, ya en uso |

### 5.1 Estructura de carpetas — Laravel (backend)

```
app/
  Http/Controllers/
    AuthController.php
    ProfileController.php
    DietController.php
    MealLogController.php
    ShoppingController.php
    StatsController.php
  Http/Requests/                    // FormRequest por endpoint de escritura
    StoreMealLogRequest.php
    UpdateProfileRequest.php
    SubstituteFoodRequest.php
  Policies/                         // Policy por modelo — autorización IDOR
    WeeklyPlanPolicy.php
    DailyLogPolicy.php
    ShoppingListPolicy.php
  Jobs/                             // Generación asíncrona
    GenerateDietJob.php
  Services/
    DietCalculator.php        // Motor de cálculo TDEE y macros
    MealDistributor.php       // Distribución de macros por comida
    DayRecalculator.php       // Recálculo por eventos del día
    SubstituteEngine.php      // Motor de sustituciones
    ShoppingGenerator.php     // Generación lista de la compra
  Models/
    User.php, Food.php, WeeklyPlan.php, DayPlan.php
    Meal.php, MealItem.php, MealLog.php, MealLogItem.php
    DailyLog.php, ShoppingList.php, ShoppingItem.php
    UserFoodRestriction.php, UserWeightLog.php
database/migrations/         // Una migración por tabla — con índices definidos
database/seeders/            // Seeder de alimentos — mínimo 25 por categoría (sección 10)
tests/Unit/Services/         // Tests unitarios del motor de cálculo
    DietCalculatorTest.php
    MealDistributorTest.php
    DayRecalculatorTest.php
```

### 5.2 Estructura de carpetas — Vue (frontend)

```
src/
  views/
    Dashboard.vue             // Vista principal del día
    WeeklyPlan.vue            // Vista del plan semanal
    DailyLog.vue              // Seguimiento del día
    Shopping.vue              // Lista de la compra
    Stats.vue                 // Estadísticas y gráficas
    Profile.vue               // Perfil y configuración
    Onboarding.vue            // Wizard 3 pasos
  components/
    layout/
      SidebarNav.vue          // Navegación desktop (DESIGN.md sección 1)
      BottomTabBar.vue        // Navegación mobile (DESIGN.md sección 1)
    MacroBar.vue              // Barra de progreso de macros (proteína/carbos/grasa/cal)
    MealCard.vue              // Tarjeta de comida
    SubstituteSelector.vue    // Componente unificado de sustitución (plan + log + compra)
    RecalcNotice.vue          // Mensaje contextual de recálculo automático
    PlanStatusPoller.vue      // Polling estado generación del plan
    WeightChart.vue           // Gráfica de evolución de peso
    AdherenceHeatmap.vue      // Heatmap SVG personalizado (no Chart.js)
  stores/                     // Pinia stores
    userStore.js
    dietStore.js
    logStore.js
    navStore.js               // Estado de navegación compartido (sidebar/bottom bar)
```

---

## 6. Seguridad y Protección de Datos

Esta sección documenta los requisitos de seguridad que deben implementarse desde la Fase 1, antes de escribir el primer controller.

### 6.1 Autorización a nivel de objeto (anti-IDOR)

Cada endpoint de la API que accede a un recurso de usuario debe verificar que el recurso pertenece al usuario autenticado. Laravel Policies son el mecanismo estándar:

- Crear una Policy por cada modelo expuesto en la API: `WeeklyPlanPolicy`, `DailyLogPolicy`, `ShoppingListPolicy`, `MealLogPolicy`
- En cada método de controller que acceda a un recurso, llamar a `$this->authorize('view', $resource)` antes de cualquier lógica
- El método `view` de cada Policy verifica: `return $user->id === $resource->user_id;`
- Laravel devuelve automáticamente HTTP 403 si la Policy falla — nunca exponer el recurso de otro usuario

Ejemplo (`WeeklyPlanPolicy`):

```php
public function view(User $user, WeeklyPlan $plan): bool
{
    return $user->id === $plan->user_id;
}
```

En el controller:

```php
public function show(WeeklyPlan $plan)
{
    $this->authorize('view', $plan);
    return new WeeklyPlanResource($plan);
}
```

### 6.2 Autenticación — Sanctum en modo SPA

- Configurar Sanctum con stateful domains (dominio de Vue) para usar autenticación por cookie httpOnly
- La cookie es httpOnly: JavaScript no puede acceder a ella — elimina el riesgo de XSS token theft
- No almacenar el token en localStorage ni sessionStorage
- Configurar CSRF token en todas las peticiones mutantes (POST, PUT, DELETE, PATCH)

### 6.3 Rate limiting

- Endpoint de login: máximo 10 intentos por minuto por IP (ThrottleRequests middleware)
- API autenticada: máximo 60 requests por minuto por usuario
- Endpoint de generación de plan: máximo 3 por hora por usuario (evita abuso del Job queue)
- Configurar en `RouteServiceProvider` con `RateLimiter::for()`

### 6.4 Validación y protección de modelos

- Definir `$fillable` explícitamente en todos los modelos Eloquent antes del primer controller — nunca usar `$guarded = []`
- Usar FormRequest dedicado por cada endpoint de escritura — la validación no va en el controller
- Los campos `food_id` recibidos del frontend para sustituciones deben validarse contra `food_substitutes`, no aceptarse directamente
- No exponer stack traces ni mensajes de error internos en respuestas de producción (`APP_DEBUG=false`)

### 6.5 Protección contra XSS

- No usar `v-html` con datos introducidos por el usuario (nombres de alimentos, notas de peso, etc.)
- Configurar `Content-Security-Policy` header en las respuestas del servidor
- Sanitizar inputs de texto libre en el backend antes de persistir

### 6.6 Protección de datos sensibles (RGPD/LOPD)

- Los campos de salud (peso, grasa corporal, alergias) se cifran con el sistema de cifrado de columnas de Laravel
- Implementar endpoint `DELETE /api/account` que elimine completamente todos los datos del usuario
- Documentar la política de privacidad antes del primer despliegue público
- Definir periodo de retención de datos y proceso de anonimización de datos históricos

---

## 7. Rendimiento y Caché

### 7.1 Estrategia de caché con Redis

Redis se usa como driver de caché de Laravel para los objetos de mayor coste computacional:

| Objeto cacheado | Clave | TTL | Invalidación |
| --- | --- | --- | --- |
| DayPlan del usuario | `dayplan:{user_id}:{fecha}` | 15 min | Al registrar cualquier evento del día (meal_log, daily_log update) |
| Lista de alimentos por categoría | `foods:category:{cat}` | 24h | Al actualizar la BD de alimentos (solo admin) |
| Restricciones del usuario | `restrictions:{user_id}` | 1h | Al modificar `user_food_restrictions` |

Implementación: usar `Cache::remember()` de Laravel. La invalidación se hace con `Cache::forget()` en los observers de los modelos correspondientes.

### 7.2 Eager loading obligatorio

Todos los endpoints que devuelvan relaciones anidadas deben usar eager loading explícito para evitar el problema N+1. Casos críticos:

- `GET /api/daily-logs/{id}`: `with(['mealLogs.mealLogItems.food', 'mealLogs.meal.mealSlot'])`
- `GET /api/plans/{id}`: `with(['dayPlans.meals.mealItems.food'])`
- `GET /api/shopping-lists/{id}`: `with(['shoppingItems.food', 'shoppingItems.substituteFood'])`

**Herramienta recomendada:** durante el desarrollo, activar Laravel Debugbar (solo en local) para detectar consultas N+1 de forma visual. En staging, usar Laravel Telescope. Configurar una alerta si cualquier request genera más de 10 queries.

### 7.3 Optimización de la base de datos

- Usar paginación en todos los endpoints de listado (nunca devolver colecciones sin límite)
- La gráfica de peso (`user_weight_logs`) debe limitar el periodo inicial a 90 días y paginar hacia atrás
- Los registros de `meal_log_items` antiguos (más de 1 año) se pueden archivar sin impactar el rendimiento habitual

---

## 8. Calidad y Testing

El motor de cálculo determinista es la parte más crítica de la lógica de negocio y la más sencilla de testear: inputs fijos producen outputs predecibles. Los tests deben escribirse antes o durante el desarrollo de cada Service, no después.

### 8.1 Tests unitarios — motor de cálculo (Pest)

| Archivo de test | Casos cubiertos |
| --- | --- |
| `DietCalculatorTest.php` | BMR correcto para hombre y mujer con valores conocidos · TDEE con cada factor de actividad · Macros objetivo para cada objetivo (volumen/mant/def) · Caso edge: peso extremo (< 40kg, > 200kg) |
| `MealDistributorTest.php` | Distribución correcta de macros entre N comidas · Pre-entreno y post-entreno reciben la proporción correcta · Con 2 comidas y con 7 comidas · La suma de macros de todas las comidas iguala el total del día |
| `DayRecalculatorTest.php` | Caso A: macros redistribuidos correctamente tras comida saltada · Caso B: reducción correcta al no entrenar · Caso C: aumento correcto al entrenar en descanso · Caso D: descuento correcto de comida extra · Caso edge: no quedan comidas restantes |
| `SubstituteEngineTest.php` | Sustituto con alergia es filtrado · Sustitutos ordenados por similitud de macros · Sin sustitutos disponibles devuelve array vacío |

### 8.2 Tests de feature — API (Pest)

- Autorización: un usuario no puede acceder al plan de otro usuario (HTTP 403)
- Autenticación: endpoints protegidos devuelven HTTP 401 sin token
- Rate limit: el endpoint de login bloquea tras 10 intentos
- Validación: inputs inválidos en FormRequest devuelven HTTP 422 con errores descriptivos
- Job: `GenerateDietJob` actualiza `weekly_plans.status` a `ready` o `failed` correctamente

---

## 9. Sistema de Diseño

> Esta sección queda **sustituida en su totalidad** por `DESIGN.md`. Se mantiene aquí solo como referencia rápida; en caso de conflicto, `DESIGN.md` prevalece.

Resumen de `DESIGN.md`:

- **Navegación responsive:** sidebar fijo en desktop (≥1024px, fondo `--color-surface-dark`), bottom tab bar en mobile (<1024px). Estado compartido vía `navStore`.
- **Paleta base cálida:** fondo crema `#FDF6F0`, superficies blancas, superficie de contraste oscuro `#1F1B16`, acento general verde pastel `#A8E063` (no naranja).
- **Tokens semánticos de macros sin cambios:** proteína `#16A34A`, carbohidratos `#EA580C` (uso exclusivo, no como acento general), grasa `#CA8A04`, calorías `#2563EB`, objetivo `#64748B`, adherencia completa `#15803D`, adherencia parcial `#86EFAC`.
- **Patrones rescatados de la referencia visual:** números grandes y bold para métricas clave con badges de variación, tarjetas de contraste oscuro para bloques destacados (heatmap, stats), badges redondeados para porcentajes/estados.
- **Tipografía:** pendiente de confirmación (Inter o Plus Jakarta Sans).

---

## 10. Base de Datos de Alimentos

La calidad de los datos de alimentos es la dependencia operativa más crítica del V1. Con una BD insuficiente, el motor generará planes repetitivos e insatisfactorios desde el primer día.

### 10.1 Mínimo viable para V1

| Categoría | Mínimo V1 | Ejemplos prioritarios |
| --- | --- | --- |
| Proteína | 25 alimentos | Pechuga de pollo, pavo, ternera magra, salmón, atún, huevos, claras, requesón, tofu, tempeh, merluza, bacalao, gambas, pechuga de pavo cocida, fiambre de pavo |
| Carbohidratos complejos | 20 alimentos | Arroz blanco, arroz integral, avena, patata, boniato, pasta, pan integral, quinoa, cuscús, legumbres (lentejas, garbanzos, alubias) |
| Verduras | 20 alimentos | Brócoli, espinacas, judías verdes, calabacín, tomate, pepino, lechuga, coliflor, pimiento, champiñones, espárragos, rúcula |
| Frutas | 15 alimentos | Plátano, manzana, pera, frutos rojos, naranja, kiwi, melón, sandía, piña, uvas, mango |
| Grasas saludables | 10 alimentos | Aceite de oliva, aguacate, almendras, nueces, anacardos, mantequilla de cacahuete, semillas de chía, semillas de lino |
| Lácteos | 10 alimentos | Yogur griego, leche semidesnatada, queso cottage, mozzarella, queso fresco, kéfir |

**Fuentes de datos nutricionales:** verificar los valores de cada alimento contra fuentes oficiales antes de incluirlo en el seeder: BEDCA (Base de Datos Española de Composición de Alimentos, bedca.net) o FoodData Central del USDA (fdc.nal.usda.gov). No usar valores de Wikipedia ni de fuentes sin respaldo científico.

### 10.2 Criterio de sustitutos

Cada alimento debe tener definidos al menos 2 sustitutos en `food_substitutes`. Criterio de similitud (`similitud_macros`): diferencia < 10% en proteína, carbos y grasa respecto al alimento original en la misma cantidad de gramos.

---

## 11. Fases de Desarrollo V1

| Fase | Módulos | Entregable | Seguridad incluida |
| --- | --- | --- | --- |
| Fase 0 — Fundación | Sistema de diseño (`DESIGN.md`) + Índices DB + Policies + Rate limiting + Tests de autorización | `tailwind.config.js` con tokens · Migraciones con índices · Policies base · Throttle middleware · Sidebar/BottomBar placeholder | **Obligatorio antes de Fase 1** |
| Fase 1 — Base | Auth + Perfil + BD alimentos (mínimo según sección 10.1) + Motor de cálculo TDEE + Tests unitarios DietCalculator | El usuario puede crear su perfil y ver sus macros objetivo calculados. Tests de DietCalculator al 100%. | Sanctum cookie mode · `$fillable` en modelos · FormRequest en endpoints |
| Fase 2 — Plan | Generación asíncrona plan semanal (Job) + Vista semanal + PlanStatusPoller + Tests MealDistributor | El usuario tiene un plan de dieta generado automáticamente. Status polling funcional. | WeeklyPlanPolicy · UUID en weekly_plans |
| Fase 3 — Seguimiento | Onboarding wizard + Registro diario de comidas + MacroBar + Registro de entreno + RecalcNotice | El usuario puede marcar comidas y ver su progreso del día en tiempo real con feedback de recálculo. | DailyLogPolicy · Eager loading en DailyLog endpoint |
| Fase 4 — Inteligencia | DayRecalculator (casos A-D) + SubstituteSelector unificado + Motor de sustituciones + Tests DayRecalculator | La app adapta el plan ante cualquier imprevisto del día con mensajes explicativos. | Validación backend de sustitutos · Tests de autorización feature |
| Fase 5 — Compra | Lista de la compra automática + Sustituciones en compra (SubstituteSelector reutilizado) | El usuario tiene su lista de la compra generada y puede personalizarla. | ShoppingListPolicy |
| Fase 6 — Stats | Registro de peso + Gráficas Chart.js + AdherenceHeatmap SVG | El usuario puede ver su evolución histórica completa. | Paginación en endpoints de histórico |

---

## 12. Ideas para Versiones Futuras

### 12.1 Integración con IA (V2)

- Generación de dietas con Claude API para mayor variedad y personalización.
- Sugerencias de sustitución más inteligentes basadas en preferencias aprendidas.
- Análisis de patrones de adherencia y recomendaciones personalizadas.

### 12.2 Funcionalidades adicionales

- Recetas: en lugar de alimentos individuales, el usuario puede añadir recetas completas con sus macros calculados.
- Escáner de código de barras para añadir alimentos de productos envasados.
- Sincronización con wearables (Garmin, Apple Watch) para importar calorías quemadas reales.
- Modo multiusuario: un nutricionista puede gestionar las dietas de varios clientes.
- App móvil (React Native o Capacitor sobre el mismo Vue).
- Exportar el plan semanal y la lista de la compra en PDF.

### 12.3 Base de datos de alimentos

- Ampliar la base inicial al catálogo completo (500+ alimentos).
- Permitir al usuario añadir alimentos personalizados con sus macros.
- Integración con APIs de nutrición como Open Food Facts.

---

## 13. Decisiones pendientes

| Decisión | Opciones | Estado |
| --- | --- | --- |
| Tipografía escogida Plus Jakarta Sans |
| Política de ajuste calórico por no entreno | ¿Reducción fija (300 kcal) o calculada según perfil? | Por validar con primeros usuarios |
| Despliegue inicial | VPS / Railway / Fly.io | Pendiente |

---

*Confidencial — uso interno*
