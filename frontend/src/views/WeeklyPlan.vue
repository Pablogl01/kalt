<script setup>
import { ref, onMounted, computed } from 'vue'
import { BarChart3, Apple, Activity, Sofa } from 'lucide-vue-next'
import { useUserStore } from '@/stores/userStore'
import { useDietStore } from '@/stores/dietStore'
import MacroBar from '@/components/MacroBar.vue'
import PlanStatusPoller from '@/components/PlanStatusPoller.vue'
import SubstituteSelector from '@/components/SubstituteSelector.vue'

const userStore = useUserStore()
const dietStore = useDietStore()

const activeDayIndex = ref(0)
const currentPlanId = ref(null)

const showSubstituteSelector = ref(false)
const selectedMealItem = ref(null)

const isAllergic = (foodId) => {
  if (!userStore.user?.food_restrictions) return false
  return userStore.user.food_restrictions.some(
    r => r.food_id === foodId && r.tipo === 'alergia'
  )
}

function triggerSubstitute(item) {
  selectedMealItem.value = item
  showSubstituteSelector.value = true
}

async function handleSubstituteSelected(updatedItem) {
  await dietStore.loadActivePlan()
}

onMounted(async () => {
  // Load active plan on mount if user is authenticated and has complete profile
  if (userStore.isAuthenticated && userStore.hasCompleteProfile) {
    await dietStore.loadActivePlan()
  }
})

// Navigation tabs for the 7 days
const daysOfWeek = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']

const currentDayPlan = computed(() => {
  if (!dietStore.activePlan || !dietStore.activePlan.day_plans) return null
  // Sort day plans by date to ensure they map Monday-Sunday
  const sortedDays = [...dietStore.activePlan.day_plans].sort(
    (a, b) => new Date(a.fecha) - new Date(b.fecha)
  )
  return sortedDays[activeDayIndex.value] || null
})

async function handleGenerate() {
  try {
    const uuid = await dietStore.generatePlan()
    currentPlanId.value = uuid
  } catch (err) {
    console.error('Failed to trigger generation:', err)
  }
}

function handlePlanReady() {
  currentPlanId.value = null
  activeDayIndex.value = 0
}

function handlePlanFailed() {
  // handled visually inside poller
}

function isTrainingDay(dateString) {
  if (!dateString || !userStore.user) return false
  const date = new Date(dateString)
  const day = date.getDay() // 0 = Sunday, 1 = Monday
  const isoDay = day === 0 ? 7 : day
  const level = userStore.user.nivel_actividad || 'sedentario'
  
  if (level === 'alto') return [1, 2, 3, 5, 6].includes(isoDay)
  if (level === 'moderado') return [1, 3, 5].includes(isoDay)
  if (level === 'ligero') return [2, 4].includes(isoDay)
  return false
}

function formatTime(timeStr) {
  if (!timeStr) return ''
  // 14:00:00 -> 14:00
  return timeStr.substring(0, 5)
}
</script>

<template>
  <div class="weekly-plan-page">
    
    <!-- ── Profile Incomplete State ─────────────────────── -->
    <div v-if="!userStore.hasCompleteProfile" class="card-empty-state">
      <div class="empty-icon"><BarChart3 :size="56" :stroke-width="1.75" aria-hidden="true" /></div>
      <h2 class="empty-title">Completa tu perfil</h2>
      <p class="empty-desc">
        Necesitamos conocer tus datos físicos y tus objetivos deportivos para poder generar un plan de alimentación adecuado para ti.
      </p>
      <router-link to="/profile" class="btn-cta">Completar mi perfil</router-link>
    </div>

    <!-- ── No Active Plan State ────────────────────────── -->
    <div v-else-if="!dietStore.activePlan && dietStore.planStatus !== 'pending' && !dietStore.isGenerating" class="card-empty-state">
      <div class="empty-icon"><Apple :size="56" :stroke-width="1.75" aria-hidden="true" /></div>
      <h2 class="empty-title">Genera tu primer plan semanal</h2>
      <p class="empty-desc">
        ¡Tu perfil está listo! Haz clic a continuación para generar tu plan de alimentación determinista completamente personalizado para 7 días.
      </p>
      <button class="btn-cta" @click="handleGenerate">Generar mi plan</button>
    </div>

    <!-- ── Plan Generating Poller State ────────────────── -->
    <div v-else-if="dietStore.planStatus === 'pending' || dietStore.isGenerating" class="poller-wrapper">
      <PlanStatusPoller 
        :planId="currentPlanId || dietStore.activePlan?.id" 
        @plan-ready="handlePlanReady"
        @plan-failed="handlePlanFailed"
      />
    </div>

    <!-- ── Active Weekly Plan State ────────────────────── -->
    <div v-else class="plan-content">
      
      <!-- Header / General Info -->
      <header class="plan-header">
        <div>
          <h1 class="plan-title">Tu semana</h1>
          <p class="plan-subtitle">Plan de alimentación adaptado a tu objetivo de <strong>{{ userStore.user?.objetivo }}</strong></p>
        </div>
        <button class="btn-regenerate" @click="handleGenerate" :disabled="dietStore.isGenerating">
          Regenerar plan
        </button>
      </header>

      <!-- Days Navigation Tabs -->
      <nav class="days-nav" aria-label="Días de la semana">
        <button
          v-for="(day, index) in daysOfWeek"
          :key="day"
          class="day-tab"
          :class="{ 'day-tab--active': activeDayIndex === index }"
          @click="activeDayIndex = index"
        >
          <span class="day-name">{{ day }}</span>
        </button>
      </nav>

      <!-- Active Day Details -->
      <main v-if="currentDayPlan" class="day-details">
        
        <!-- Day summary card -->
        <div class="day-summary-card">
          <div class="summary-info">
            <span class="summary-date">{{ currentDayPlan.fecha }}</span>
            <div class="training-badge-wrap">
              <span v-if="isTrainingDay(currentDayPlan.fecha)" class="badge badge--training">
                <Activity :size="14" :stroke-width="2" aria-hidden="true" /> Día de entreno
              </span>
              <span v-else class="badge badge--rest">
                <Sofa :size="14" :stroke-width="2" aria-hidden="true" /> Día de descanso
              </span>
            </div>
          </div>
          
          <div class="day-macros-grid">
            <div class="day-macro-stat">
              <span class="stat-num">{{ Math.round(currentDayPlan.calorias_objetivo) }}</span>
              <span class="stat-unit">kcal</span>
            </div>
            <div class="day-macro-stat font-protein">
              <span class="stat-num">{{ Math.round(currentDayPlan.proteina_obj) }}</span>
              <span class="stat-unit">g prot</span>
            </div>
            <div class="day-macro-stat font-carbs">
              <span class="stat-num">{{ Math.round(currentDayPlan.carbos_obj) }}</span>
              <span class="stat-unit">g carb</span>
            </div>
            <div class="day-macro-stat font-fat">
              <span class="stat-num">{{ Math.round(currentDayPlan.grasa_obj) }}</span>
              <span class="stat-unit">g grasa</span>
            </div>
          </div>
        </div>

        <!-- Meals List -->
        <section class="meals-list">
          <article 
            v-for="meal in currentDayPlan.meals" 
            :key="meal.id"
            class="meal-card"
          >
            <div class="meal-header">
              <div>
                <h3 class="meal-name">{{ meal.nombre }}</h3>
                <span class="meal-time">{{ formatTime(meal.hora_objetivo) }}</span>
              </div>
              <div class="meal-macro-bar-wrap">
                <MacroBar :macros="meal.meal_items.reduce((acc, item) => {
                  acc.calorias += parseFloat(item.calorias);
                  acc.proteina += parseFloat(item.proteina);
                  acc.carbos += parseFloat(item.carbos);
                  acc.grasa += parseFloat(item.grasa);
                  return acc;
                }, { calorias: 0, proteina: 0, carbos: 0, grasa: 0 })" />
              </div>
            </div>

            <!-- Meal Items Table/List -->
            <ul class="meal-items">
              <li 
                v-for="item in meal.meal_items" 
                :key="item.id"
                class="meal-item"
              >
                <div class="item-info">
                  <span class="item-name">{{ item.food?.nombre }}</span>
                  <span class="item-qty">{{ Math.round(item.cantidad_gramos) }}g</span>
                </div>
                <div class="item-macros">
                  <span class="item-macro font-protein">{{ Math.round(item.proteina) }}g P</span>
                  <span class="item-macro font-carbs">{{ Math.round(item.carbos) }}g C</span>
                  <span class="item-macro font-fat">{{ Math.round(item.grasa) }}g G</span>
                  <span class="item-macro font-calories">{{ Math.round(item.calorias) }} kcal</span>
                </div>
                <button 
                  v-if="!isAllergic(item.food_id)" 
                  @click="triggerSubstitute(item)" 
                  class="btn-substitute" 
                  title="Sustituir alimento"
                >
                  Sustituir
                </button>
              </li>
            </ul>
          </article>
        </section>
      </main>

    </div>

    <!-- Substitute Selector Modal -->
    <SubstituteSelector
      v-if="showSubstituteSelector && selectedMealItem"
      :mealItemId="selectedMealItem.id"
      :foodName="selectedMealItem.food?.nombre"
      context="plan"
      @selected="handleSubstituteSelected"
      @close="showSubstituteSelector = false; selectedMealItem = null"
    />
  </div>
</template>

<style scoped>
.weekly-plan-page {
  max-width: 800px;
  margin: 0 auto;
  padding: var(--space-6) var(--space-5) var(--space-7);
}

/* ── Empty State / CTA Cards ──────────────────────── */
.card-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: var(--space-7) var(--space-6);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  border: 1px solid var(--border-subtle);
  margin-top: var(--space-6);
}

.empty-icon {
  display: flex;
  justify-content: center;
  color: var(--color-text-muted);
  margin-bottom: var(--space-5);
}

.empty-title {
  font-size: var(--fs-xl);
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 var(--space-3);
}

.empty-desc {
  font-size: var(--fs-base);
  color: var(--color-text-muted);
  line-height: 1.6;
  max-width: 460px;
  margin: 0 0 var(--space-6);
}

.btn-cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-3) var(--space-6);
  background: var(--color-accent);
  color: var(--color-text);
  font-weight: 700;
  border: none;
  border-radius: var(--radius-md);
  text-decoration: none;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
}

.btn-cta:hover {
  background: var(--color-accent-dark);
}

.btn-cta:active {
  transform: scale(0.98);
}

/* ── Weekly Plan Layout ───────────────────────────── */
.plan-content {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.plan-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-4);
  flex-wrap: wrap;
}

.plan-title {
  font-size: var(--fs-2xl);
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 var(--space-1);
}

.plan-subtitle {
  font-size: var(--fs-base);
  color: var(--color-text-muted);
  margin: 0;
}

.btn-regenerate {
  padding: var(--space-3) var(--space-5);
  background: transparent;
  color: var(--color-text);
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  font-weight: 600;
  font-size: var(--fs-sm);
  cursor: pointer;
  transition: all 0.15s;
}

.btn-regenerate:hover {
  background: var(--color-surface);
  border-color: var(--color-text-muted);
}

/* ── Days Navigation Tabs ─────────────────────────── */
.days-nav {
  display: flex;
  gap: var(--space-2);
  overflow-x: auto;
  padding-bottom: var(--space-1);
  scrollbar-width: none; /* Firefox */
}

.days-nav::-webkit-scrollbar {
  display: none; /* Chrome/Safari */
}

.day-tab {
  flex: 1;
  min-width: 80px;
  padding: var(--space-3) var(--space-2);
  background: var(--color-surface);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-md);
  cursor: pointer;
  text-align: center;
  transition: all 0.15s;
}

.day-tab--active {
  background: var(--color-surface-dark);
  border-color: var(--color-surface-dark);
}

.day-tab--active .day-name {
  color: var(--color-surface);
  font-weight: 700;
}

.day-name {
  font-size: var(--fs-sm);
  font-weight: 500;
  color: var(--color-text);
}

/* ── Day Details & Summary ────────────────────────── */
.day-details {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.day-summary-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-5) var(--space-5);
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-subtle);
  box-shadow: var(--shadow-md);
  flex-wrap: wrap;
  gap: var(--space-4);
}

.summary-info {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.summary-date {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
  font-weight: 500;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-lg);
  font-size: var(--fs-xs);
  font-weight: 600;
}

.badge--training {
  background: rgba(168, 224, 99, 0.15);
  color: var(--color-text);
}

.badge--rest {
  background: rgba(138, 129, 120, 0.15);
  color: var(--color-text-muted);
}

.day-macros-grid {
  display: flex;
  gap: var(--space-5);
}

.day-macro-stat {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.stat-num {
  font-size: var(--fs-xl);
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.1;
}

.stat-unit {
  font-size: var(--fs-xs);
  color: var(--color-text-muted);
  font-weight: 500;
}

/* ── Meals list & Meal Cards ──────────────────────── */
.meals-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.meal-card {
  background: var(--color-surface);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-subtle);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}

.meal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-5) var(--space-5);
  border-bottom: 1px solid var(--border-subtle);
  flex-wrap: wrap;
  gap: var(--space-4);
}

.meal-name {
  font-size: var(--fs-lg);
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 var(--space-1);
}

.meal-time {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
  font-weight: 500;
}

.meal-macro-bar-wrap {
  width: 280px;
}

@media (max-width: 480px) {
  .meal-macro-bar-wrap {
    width: 100%;
  }
}

.meal-items {
  list-style: none;
  padding: 0;
  margin: 0;
}

.meal-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--border-subtle);
  flex-wrap: wrap;
  gap: var(--space-3);
}

.meal-item:last-child {
  border-bottom: none;
}

.item-info {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 150px;
}

.item-name {
  font-size: var(--fs-base);
  font-weight: 600;
  color: var(--color-text);
}

.item-qty {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
}

.item-macros {
  display: flex;
  gap: var(--space-3);
  font-size: var(--fs-sm);
}

.item-macro {
  font-weight: 500;
}

.btn-substitute {
  padding: var(--space-2) var(--space-3);
  background: var(--color-bg);
  border: 1px solid var(--border-default);
  color: var(--color-system);
  font-size: var(--fs-xs);
  font-weight: 600;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-substitute:hover {
  background-color: rgba(37, 99, 235, 0.08);
  border-color: var(--color-system);
}

/* ── Typography modifications ─────────────────────── */
.font-protein { color: var(--color-protein); }
.font-carbs { color: var(--color-carbs); }
.font-fat { color: var(--color-fat); }
.font-calories { color: var(--color-calories); }
</style>
