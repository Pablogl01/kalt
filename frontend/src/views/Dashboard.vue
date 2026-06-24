<script setup>
import { onMounted, computed, ref } from 'vue'
import { motion, AnimatePresence } from 'motion-v'
import { BarChart3, Utensils, Activity, Check, Scale, TrendingUp, TrendingDown, Minus } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/userStore'
import { useDietStore } from '@/stores/dietStore'
import { useLogStore } from '@/stores/logStore'
import { spring, tap, listContainer, listItem, sheet } from '@/lib/motion'
import api from '@/api/client'
import AnimatedNumber from '@/components/AnimatedNumber.vue'
import MacroBar from '@/components/MacroBar.vue'
import WeightChart from '@/components/WeightChart.vue'
import PlanStatusPoller from '@/components/PlanStatusPoller.vue'

// Staggered "pop" for the weekly adherence cells.
const adherenceCells = listContainer
const cellPop = {
  hidden: { opacity: 0, scale: 0.5 },
  show: { opacity: 1, scale: 1, transition: spring.snappy },
}

const router = useRouter()
const userStore = useUserStore()
const dietStore = useDietStore()
const logStore = useLogStore()

const loading = ref(false)

// Get today's date formatted as YYYY-MM-DD
const todayDateString = computed(() => {
  const today = new Date()
  const year = today.getFullYear()
  const month = String(today.getMonth() + 1).padStart(2, '0')
  const day = String(today.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
})

// ── Insight widgets (weight trend + weekly adherence) ──────
const weightLogs = ref([])
const adherenceDays = ref([])

// Build a {from, to} window of the last `days` days as YYYY-MM-DD strings.
const rangeDates = (days) => {
  const to = new Date()
  const from = new Date()
  from.setDate(to.getDate() - (days - 1))
  return { from: from.toLocaleDateString('en-CA'), to: to.toLocaleDateString('en-CA') }
}

async function fetchInsights() {
  const w = rangeDates(90)
  const a = rangeDates(7)
  try {
    const [weightRes, adherenceRes] = await Promise.all([
      api.get(`/stats/weight?from=${w.from}&to=${w.to}`),
      api.get(`/stats/adherence?from=${a.from}&to=${a.to}`),
    ])
    weightLogs.value = weightRes.data
    adherenceDays.value = adherenceRes.data
  } catch (err) {
    console.error('Error fetching dashboard insights:', err)
  }
}

onMounted(async () => {
  loading.value = true
  try {
    await userStore.fetchProfile()
    if (userStore.hasCompleteProfile) {
      await dietStore.loadActivePlan()
      if (dietStore.activePlan) {
        await logStore.fetchDailyLog(todayDateString.value)
      }
      // Insights are independent of the plan; load them in the background.
      fetchInsights()
    }
  } catch (err) {
    console.error('Error initializing Dashboard:', err)
  } finally {
    loading.value = false
  }
})

// Weight figures derived from the fetched logs (sorted ascending by the API).
const currentWeight = computed(() =>
  weightLogs.value.length ? weightLogs.value[weightLogs.value.length - 1].peso : null
)
const weightDelta = computed(() => {
  if (weightLogs.value.length < 2) return null
  const first = weightLogs.value[0].peso
  const last = weightLogs.value[weightLogs.value.length - 1].peso
  return +(last - first).toFixed(1)
})

// Weekly adherence: % of tracked days fully completed.
const completedDays = computed(() => adherenceDays.value.filter(d => d.nivel === 'completa').length)
const adherencePct = computed(() => {
  const tracked = adherenceDays.value.filter(d => d.nivel !== 'sin_datos').length
  if (!tracked) return 0
  return Math.round((completedDays.value / tracked) * 100)
})

const WEEKDAY_INITIALS = ['D', 'L', 'M', 'X', 'J', 'V', 'S']
const dayInitial = (fecha) => {
  const [y, m, d] = fecha.split('-').map(Number)
  return WEEKDAY_INITIALS[new Date(y, m - 1, d).getDay()]
}

// Calculations for today's macros
const targetMacros = computed(() => {
  if (logStore.dailyLog?.day_plan) {
    return {
      calorias: parseFloat(logStore.dailyLog.day_plan.calorias_objetivo || 0),
      proteina: parseFloat(logStore.dailyLog.day_plan.proteina_obj || 0),
      carbos: parseFloat(logStore.dailyLog.day_plan.carbos_obj || 0),
      grasa: parseFloat(logStore.dailyLog.day_plan.grasa_obj || 0),
    }
  }
  return {
    calorias: parseFloat(userStore.macros?.calorias || 2000),
    proteina: parseFloat(userStore.macros?.proteina || 150),
    carbos: parseFloat(userStore.macros?.carbos || 200),
    grasa: parseFloat(userStore.macros?.grasa || 60),
  }
})

const consumedMacros = computed(() => {
  let protein = 0
  let carbs = 0
  let fat = 0
  let calories = 0

  logStore.mealLogs.forEach(ml => {
    if (ml.realizada || ml.es_extra) {
      ml.meal_log_items?.forEach(item => {
        protein += parseFloat(item.proteina || 0)
        carbs += parseFloat(item.carbos || 0)
        fat += parseFloat(item.grasa || 0)
        calories += parseFloat(item.calorias || 0)
      })
    }
  })

  return {
    calorias: calories,
    proteina: protein,
    carbos: carbs,
    grasa: fat,
  }
})

async function triggerGenerate() {
  try {
    await dietStore.generatePlan()
  } catch (err) {
    console.error('Failed to trigger plan generation:', err)
  }
}

function handlePlanReady() {
  dietStore.loadActivePlan().then(() => {
    logStore.fetchDailyLog(todayDateString.value)
  })
}

const formatTime = (timeStr) => {
  if (!timeStr) return ''
  const parts = timeStr.split(':')
  return `${parts[0]}:${parts[1]}`
}
</script>

<template>
  <div class="dashboard-page">
    <header class="dashboard-header">
      <h1 class="welcome-title">Hola, {{ userStore.user?.name || 'Usuario' }}</h1>
      <p class="welcome-subtitle">Este es el estado de tu nutrición para el día de hoy.</p>
    </header>

    <div v-if="loading" class="dashboard-loading">
      <div class="spinner"></div>
      <p>Cargando tu información...</p>
    </div>

    <div v-else class="dashboard-content">
      
      <!-- ── CASE 1: Incomplete Profile ────────────────────── -->
      <motion.div v-if="!userStore.hasCompleteProfile" class="dashboard-card cta-card" :variants="sheet" initial="hidden" animate="show">
        <div class="card-icon-big"><BarChart3 :size="48" :stroke-width="2" aria-hidden="true" /></div>
        <h2 class="card-title">Completa tu perfil</h2>
        <p class="card-desc">Necesitamos algunos datos físicos mínimos para calcular tus macros objetivo y generar tu plan personalizado.</p>
        <router-link :to="{ name: 'onboarding' }" class="btn-cta">
          Comenzar Onboarding
        </router-link>
      </motion.div>

      <!-- ── CASE 2: Complete Profile but No Active Plan ────── -->
      <motion.div v-else-if="!dietStore.activePlan" class="dashboard-card cta-card" :variants="sheet" initial="hidden" animate="show">
        <div v-if="dietStore.planStatus === 'pending'" class="poller-wrapper">
          <PlanStatusPoller 
            :planId="dietStore.activePlan?.id || 'new-generation'" 
            @plan-ready="handlePlanReady" 
            @plan-failed="triggerGenerate" 
          />
        </div>
        <div v-else class="generate-plan-prompt">
          <div class="card-icon-big"><Utensils :size="48" :stroke-width="2" aria-hidden="true" /></div>
          <h2 class="card-title">Genera tu plan semanal</h2>
          <p class="card-desc">Tu perfil está listo, pero aún no tienes un plan de alimentación generado para esta semana.</p>
          <motion.button :while-press="dietStore.isGenerating ? undefined : tap" @click="triggerGenerate" class="btn-cta" :disabled="dietStore.isGenerating">
            {{ dietStore.isGenerating ? 'Generando...' : 'Generar mi plan' }}
          </motion.button>
        </div>
      </motion.div>

      <!-- ── CASE 3: Active Plan & Daily Logs Loaded ────────── -->
      <motion.div v-else class="dashboard-grid" :variants="listContainer" initial="hidden" animate="show">
        <!-- Macros Progress Widget -->
        <motion.section class="widget-card macros-widget" :variants="listItem">
          <h2 class="widget-title">Progreso nutricional de hoy</h2>
          <MacroBar :macros="targetMacros" :consumed="consumedMacros" />

          <div class="tracking-link-wrap">
            <router-link :to="{ name: 'daily-log' }" class="btn-link-tracking">
              Ir al seguimiento diario →
            </router-link>
          </div>
        </motion.section>

        <!-- Training Status Widget -->
        <motion.section class="widget-card training-widget" :variants="listItem">
          <h2 class="widget-title">Entrenamiento</h2>
          <div class="training-summary">
            <div class="t-icon"><Activity :size="32" :stroke-width="2" aria-hidden="true" /></div>
            <div class="t-info">
              <h3 class="t-status">
                {{ logStore.dailyLog?.ha_entrenado ? 'Entrenado' : 'Día de descanso / No entrenado' }}
              </h3>
              <p class="t-detail">
                {{ logStore.dailyLog?.entreno_planificado ? 'Entrenamiento planificado para hoy.' : 'No tenías entrenos planificados hoy.' }}
              </p>
            </div>
          </div>
        </motion.section>

        <!-- Meals Preview Widget -->
        <motion.section class="widget-card meals-widget" :variants="listItem">
          <h2 class="widget-title">Comidas del día</h2>
          <div class="meals-list-preview">
            <motion.div
              v-for="ml in logStore.mealLogs"
              :key="ml.id"
              layout
              :transition="spring.gentle"
              class="meal-preview-item"
              :class="{ 'meal-preview-item--completed': ml.realizada }"
            >
              <div class="mp-check">
                <span class="check-circle"><Check v-if="ml.realizada" :size="13" :stroke-width="3" aria-hidden="true" /></span>
              </div>
              <div class="mp-info">
                <h4 class="mp-name">{{ ml.meal?.nombre || 'Comida Extra' }}</h4>
                <span v-if="ml.meal?.hora_objetivo" class="mp-time">
                  {{ formatTime(ml.meal.hora_objetivo) }}
                </span>
              </div>
            </motion.div>

            <div v-if="logStore.mealLogs.length === 0" class="empty-preview">
              <p>No hay comidas registradas para hoy.</p>
            </div>
          </div>
        </motion.section>

        <!-- Weight Trend Widget -->
        <motion.section class="widget-card weight-widget" :variants="listItem">
          <div class="widget-head">
            <h2 class="widget-title widget-title--inline">Peso corporal</h2>
            <router-link :to="{ name: 'stats' }" class="widget-link">Ver →</router-link>
          </div>

          <div v-if="weightLogs.length >= 2" class="weight-body">
            <div class="weight-figures">
              <span class="weight-current">{{ currentWeight }}<small> kg</small></span>
              <span
                v-if="weightDelta !== null"
                class="weight-delta"
                :class="weightDelta < 0 ? 'is-down' : weightDelta > 0 ? 'is-up' : 'is-flat'"
              >
                <component :is="weightDelta < 0 ? TrendingDown : weightDelta > 0 ? TrendingUp : Minus" :size="15" :stroke-width="2.5" aria-hidden="true" />
                {{ weightDelta > 0 ? '+' : '' }}{{ weightDelta }} kg
              </span>
            </div>
            <div class="weight-chart-wrap"><WeightChart :logs="weightLogs" /></div>
          </div>

          <div v-else class="widget-empty">
            <Scale :size="28" :stroke-width="1.75" aria-hidden="true" />
            <p>Registra tu peso para ver tu evolución.</p>
            <router-link :to="{ name: 'stats' }" class="btn-link-tracking">Registrar peso →</router-link>
          </div>
        </motion.section>

        <!-- Weekly Adherence Widget -->
        <motion.section class="widget-card adherence-widget" :variants="listItem">
          <div class="widget-head">
            <h2 class="widget-title widget-title--inline">Adherencia semanal</h2>
            <router-link :to="{ name: 'stats' }" class="widget-link">Ver →</router-link>
          </div>

          <div class="adherence-body">
            <div class="adherence-summary">
              <span class="adherence-pct"><AnimatedNumber :value="adherencePct" /><small>%</small></span>
              <span class="adherence-caption">{{ completedDays }} de 7 días completos</span>
            </div>

            <motion.div class="adherence-week" :variants="adherenceCells" initial="hidden" animate="show">
              <motion.div
                v-for="day in adherenceDays"
                :key="day.fecha"
                class="adh-day"
                :title="day.fecha"
                :variants="cellPop"
              >
                <span class="adh-cell" :class="`adh-cell--${day.nivel}`"></span>
                <span class="adh-label">{{ dayInitial(day.fecha) }}</span>
              </motion.div>
            </motion.div>

            <div class="adherence-legend">
              <span><i class="lg lg--completa"></i> Completo</span>
              <span><i class="lg lg--parcial"></i> Parcial</span>
              <span><i class="lg lg--sin_datos"></i> Sin datos</span>
            </div>
          </div>
        </motion.section>
      </motion.div>

    </div>
  </div>
</template>

<style scoped>
.dashboard-page {
  max-width: 1180px;
  margin: 0 auto;
  padding: var(--space-6) var(--space-5) var(--space-7);
  display: flex;
  flex-direction: column;
  gap: var(--space-6);
}

.dashboard-header {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.welcome-title {
  font-size: var(--fs-2xl);
  font-weight: var(--fw-bold);
  line-height: var(--lh-tight);
  color: var(--color-text);
  margin: 0;
}

.welcome-subtitle {
  color: var(--color-text-muted);
  font-size: var(--fs-base);
  margin: 0;
}

.dashboard-loading {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  gap: var(--space-4);
  color: var(--color-text-muted);
}

.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid var(--border-subtle);
  border-top-color: var(--color-accent);
  border-radius: var(--radius-full);
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Onboarding CTAs */
.dashboard-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-7) var(--space-6);
  box-shadow: var(--shadow-md);
  border: 1px solid var(--border-default);
  text-align: center;
}

.cta-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
  max-width: 620px;
  margin: 0 auto;
  width: 100%;
}

.card-icon-big {
  display: flex;
  justify-content: center;
  color: var(--color-text-muted);
  margin-bottom: var(--space-2);
}

.card-title {
  font-size: var(--fs-xl);
  font-weight: var(--fw-bold);
  line-height: var(--lh-snug);
  color: var(--color-text);
  margin: 0;
}

.card-desc {
  font-size: var(--fs-base);
  color: var(--color-text-muted);
  line-height: var(--lh-normal);
  max-width: 400px;
  margin: 0 0 var(--space-4);
}

.btn-cta {
  padding: var(--space-3) var(--space-6);
  background-color: var(--color-accent);
  color: var(--color-text);
  border: none;
  font-weight: var(--fw-bold);
  border-radius: var(--radius-md);
  cursor: pointer;
  text-decoration: none;
  font-size: var(--fs-base);
  transition: background 0.2s;
  display: inline-block;
}

.btn-cta:hover {
  background-color: var(--color-accent-dark);
}

.poller-wrapper {
  width: 100%;
}

.generate-plan-prompt {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
}

/* Grid Dashboard */
.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-5);
}

/* Tablet: two columns; macros + adherence span the full width. */
@media (min-width: 768px) and (max-width: 1023px) {
  .dashboard-grid {
    grid-template-columns: 1fr 1fr;
  }
  .macros-widget,
  .adherence-widget {
    grid-column: span 2;
  }
}

/* Desktop: 6-column grid — three cards on top, peso + adherencia below. */
@media (min-width: 1024px) {
  .dashboard-grid {
    grid-template-columns: repeat(6, 1fr);
    align-items: start;
  }
  .macros-widget,
  .training-widget,
  .meals-widget,
  .weight-widget {
    grid-column: span 2;
  }
  .adherence-widget {
    grid-column: span 4;
  }
}

.widget-card {
  background: var(--color-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  padding: var(--space-5);
}

.widget-title {
  font-size: var(--fs-base);
  font-weight: var(--fw-bold);
  color: var(--color-text);
  margin: 0 0 var(--space-5);
  border-bottom: 1px solid var(--border-subtle);
  padding-bottom: var(--space-3);
}

/* Macros widget specific */
.tracking-link-wrap {
  margin-top: var(--space-5);
  display: flex;
  justify-content: flex-end;
}

.btn-link-tracking {
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--color-accent-dark);
  text-decoration: none;
}

.btn-link-tracking:hover {
  text-decoration: underline;
}

/* Training widget specific */
.training-summary {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}

.t-icon {
  color: var(--color-text-muted);
}

.t-status {
  font-size: var(--fs-base);
  font-weight: var(--fw-semibold);
  color: var(--color-text);
  margin: 0 0 var(--space-1);
}

.t-detail {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
  margin: 0;
}

/* Meals widget specific */
.meals-list-preview {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.meal-preview-item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  background-color: var(--color-bg);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-subtle);
}

.meal-preview-item--completed {
  border-color: rgba(22, 163, 74, 0.2);
  background-color: rgba(22, 163, 74, 0.02);
}

.check-circle {
  width: 20px;
  height: 20px;
  border-radius: var(--radius-full);
  border: 1.5px solid var(--border-strong);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-protein);
}

.meal-preview-item--completed .check-circle {
  background-color: var(--color-protein);
  border-color: var(--color-protein);
  color: white;
}

.mp-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex: 1;
}

.mp-name {
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--color-text);
  margin: 0;
}

.mp-time {
  font-size: var(--fs-xs);
  color: var(--color-text-muted);
}

.empty-preview {
  text-align: center;
  padding: var(--space-4);
  color: var(--color-text-muted);
  font-size: var(--fs-sm);
}

/* Widget header with inline action link (weight + adherence cards) */
.widget-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 0 0 var(--space-5);
  border-bottom: 1px solid var(--border-subtle);
  padding-bottom: var(--space-3);
}

.widget-title--inline {
  margin: 0;
  border-bottom: none;
  padding-bottom: 0;
}

.widget-link {
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--color-accent-dark);
  text-decoration: none;
  white-space: nowrap;
}

.widget-link:hover {
  text-decoration: underline;
}

/* Empty state shared by insight widgets */
.widget-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--space-2);
  padding: var(--space-5) var(--space-3);
  color: var(--color-text-muted);
  font-size: var(--fs-sm);
}

/* ── Weight widget ───────────────────────────────── */
.weight-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.weight-figures {
  display: flex;
  align-items: baseline;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.weight-current {
  font-size: var(--fs-2xl);
  font-weight: var(--fw-bold);
  color: var(--color-text);
  line-height: 1;
}

.weight-current small {
  font-size: var(--fs-base);
  font-weight: var(--fw-semibold);
  color: var(--color-text-muted);
}

.weight-delta {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-sm);
}

.weight-delta.is-down { color: var(--color-protein); background: rgba(22, 163, 74, 0.1); }
.weight-delta.is-up   { color: var(--color-carbs);   background: rgba(234, 88, 12, 0.1); }
.weight-delta.is-flat { color: var(--color-text-muted); background: rgba(138, 129, 120, 0.12); }

.weight-chart-wrap {
  height: 160px;
  width: 100%;
}

/* ── Weekly adherence widget ─────────────────────── */
.adherence-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.adherence-summary {
  display: flex;
  align-items: baseline;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.adherence-pct {
  font-size: var(--fs-2xl);
  font-weight: var(--fw-bold);
  color: var(--color-accent-dark);
  line-height: 1;
}

.adherence-pct small {
  font-size: var(--fs-lg);
}

.adherence-caption {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
}

.adherence-week {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: var(--space-2);
}

.adh-day {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
}

.adh-cell {
  width: 100%;
  aspect-ratio: 1 / 1;
  max-width: 44px;
  border-radius: var(--radius-md);
  background: rgba(138, 129, 120, 0.12);
}

.adh-cell--completa  { background: var(--color-accent); }
.adh-cell--parcial   { background: rgba(234, 88, 12, 0.45); }
.adh-cell--sin_datos { background: rgba(138, 129, 120, 0.12); }

.adh-label {
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
  color: var(--color-text-muted);
}

.adherence-legend {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  font-size: var(--fs-xs);
  color: var(--color-text-muted);
}

.adherence-legend span {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
}

.lg {
  width: 10px;
  height: 10px;
  border-radius: var(--radius-sm);
  display: inline-block;
}

.lg--completa  { background: var(--color-accent); }
.lg--parcial   { background: rgba(234, 88, 12, 0.45); }
.lg--sin_datos { background: rgba(138, 129, 120, 0.25); }
</style>
