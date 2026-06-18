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
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 250px;
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
</style>
