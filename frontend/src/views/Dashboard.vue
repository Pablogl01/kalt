<script setup>
import { onMounted, computed, ref } from 'vue'
import { BarChart3, Utensils, Activity, Check } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/userStore'
import { useDietStore } from '@/stores/dietStore'
import { useLogStore } from '@/stores/logStore'
import MacroBar from '@/components/MacroBar.vue'
import PlanStatusPoller from '@/components/PlanStatusPoller.vue'

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

onMounted(async () => {
  loading.value = true
  try {
    await userStore.fetchProfile()
    if (userStore.hasCompleteProfile) {
      await dietStore.loadActivePlan()
      if (dietStore.activePlan) {
        await logStore.fetchDailyLog(todayDateString.value)
      }
    }
  } catch (err) {
    console.error('Error initializing Dashboard:', err)
  } finally {
    loading.value = false
  }
})

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
      <div v-if="!userStore.hasCompleteProfile" class="dashboard-card cta-card">
        <div class="card-icon-big"><BarChart3 :size="48" :stroke-width="2" aria-hidden="true" /></div>
        <h2 class="card-title">Completa tu perfil</h2>
        <p class="card-desc">Necesitamos algunos datos físicos mínimos para calcular tus macros objetivo y generar tu plan personalizado.</p>
        <router-link :to="{ name: 'onboarding' }" class="btn-cta">
          Comenzar Onboarding
        </router-link>
      </div>

      <!-- ── CASE 2: Complete Profile but No Active Plan ────── -->
      <div v-else-if="!dietStore.activePlan" class="dashboard-card cta-card">
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
          <button @click="triggerGenerate" class="btn-cta" :disabled="dietStore.isGenerating">
            {{ dietStore.isGenerating ? 'Generando...' : 'Generar mi plan' }}
          </button>
        </div>
      </div>

      <!-- ── CASE 3: Active Plan & Daily Logs Loaded ────────── -->
      <div v-else class="dashboard-grid">
        <!-- Macros Progress Widget -->
        <section class="widget-card macros-widget">
          <h2 class="widget-title">Progreso nutricional de hoy</h2>
          <MacroBar :macros="targetMacros" :consumed="consumedMacros" />
          
          <div class="tracking-link-wrap">
            <router-link :to="{ name: 'daily-log' }" class="btn-link-tracking">
              Ir al seguimiento diario →
            </router-link>
          </div>
        </section>

        <!-- Training Status Widget -->
        <section class="widget-card training-widget">
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
        </section>

        <!-- Meals Preview Widget -->
        <section class="widget-card meals-widget">
          <h2 class="widget-title">Comidas del día</h2>
          <div class="meals-list-preview">
            <div 
              v-for="ml in logStore.mealLogs" 
              :key="ml.id" 
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
            </div>
            
            <div v-if="logStore.mealLogs.length === 0" class="empty-preview">
              <p>No hay comidas registradas para hoy.</p>
            </div>
          </div>
        </section>
      </div>

    </div>
  </div>
</template>

<style scoped>
.dashboard-page {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem 1.25rem 4rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.dashboard-header {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.welcome-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.welcome-subtitle {
  color: var(--color-text-muted);
  font-size: 0.9375rem;
  margin: 0;
}

.dashboard-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 250px;
  gap: 1rem;
  color: var(--color-text-muted);
}

.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid rgba(138, 129, 120, 0.15);
  border-top-color: var(--color-accent);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Onboarding CTAs */
.dashboard-card {
  background: var(--color-surface);
  border-radius: 20px;
  padding: 3rem 2rem;
  box-shadow: 0 4px 20px rgba(31, 27, 22, 0.05);
  border: 1px solid rgba(138, 129, 120, 0.12);
  text-align: center;
}

.cta-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.card-icon-big {
  display: flex;
  justify-content: center;
  color: var(--color-text-muted);
  margin-bottom: 0.5rem;
}

.card-title {
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.card-desc {
  font-size: 0.9375rem;
  color: var(--color-text-muted);
  line-height: 1.5;
  max-width: 400px;
  margin: 0 0 1rem;
}

.btn-cta {
  padding: 0.875rem 2rem;
  background-color: var(--color-accent);
  color: var(--color-text);
  border: none;
  font-weight: 700;
  border-radius: 12px;
  cursor: pointer;
  text-decoration: none;
  font-size: 0.9375rem;
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
  gap: 1rem;
}

/* Grid Dashboard */
.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}

@media (min-width: 768px) {
  .dashboard-grid {
    grid-template-columns: 1fr 1fr;
  }
  .macros-widget {
    grid-column: span 2;
  }
}

.widget-card {
  background: var(--color-surface);
  border: 1px solid rgba(138, 129, 120, 0.12);
  border-radius: 16px;
  padding: 1.5rem;
}

.widget-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 1.25rem;
  border-bottom: 1px solid rgba(138, 129, 120, 0.08);
  padding-bottom: 0.75rem;
}

/* Macros widget specific */
.tracking-link-wrap {
  margin-top: 1.5rem;
  display: flex;
  justify-content: flex-end;
}

.btn-link-tracking {
  font-size: 0.875rem;
  font-weight: 600;
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
  gap: 1rem;
}

.t-icon {
  font-size: 2rem;
}

.t-status {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 0.25rem;
}

.t-detail {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  margin: 0;
}

/* Meals widget specific */
.meals-list-preview {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.meal-preview-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background-color: var(--color-bg);
  padding: 0.625rem 0.875rem;
  border-radius: 10px;
  border: 1px solid rgba(138, 129, 120, 0.08);
}

.meal-preview-item--completed {
  border-color: rgba(22, 163, 74, 0.2);
  background-color: rgba(22, 163, 74, 0.02);
}

.check-circle {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 1.5px solid rgba(138, 129, 120, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
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
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
}

.mp-time {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.empty-preview {
  text-align: center;
  padding: 1rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}
</style>
