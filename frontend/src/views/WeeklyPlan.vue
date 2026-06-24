<script setup>
import { ref, onMounted, computed, nextTick } from 'vue'
import { motion, AnimatePresence } from 'motion-v'
import { BarChart3, Apple, Activity, Sofa, RefreshCw } from 'lucide-vue-next'
import { useUserStore } from '@/stores/userStore'
import { useDietStore } from '@/stores/dietStore'
import { spring, tap, tapSubtle, listContainer, listItem, page } from '@/lib/motion'
import AnimatedNumber from '@/components/AnimatedNumber.vue'
import MacroBar from '@/components/MacroBar.vue'
import PlanStatusPoller from '@/components/PlanStatusPoller.vue'
import SubstituteSelector from '@/components/SubstituteSelector.vue'

const userStore = useUserStore()
const dietStore = useDietStore()

const activeDayIndex = ref(0)
const currentPlanId = ref(null)

const showSubstituteSelector = ref(false)
const selectedMealItem = ref(null)
const regeneratingMealId = ref(null)

async function handleRegenerateMeal(mealId) {
  if (regeneratingMealId.value) return
  regeneratingMealId.value = mealId
  try {
    await dietStore.regenerateMeal(mealId)
  } catch (err) {
    console.error('Failed to regenerate meal:', err)
  } finally {
    regeneratingMealId.value = null
  }
}

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
  // Fetch the plan only if we don't already have it (first entry).
  if (userStore.isAuthenticated && userStore.hasCompleteProfile && !dietStore.activePlan) {
    await dietStore.loadActivePlan()
  }
  // Always open on today: the view remounts on every navigation here, so this
  // runs each time, snapping back to the current day even after a manual pick.
  selectDay(todayIndex())
})

// Navigation tabs for the 7 days
const daysOfWeek = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']

// Index of today's day within the sorted week, or 0 (Monday) if the plan's
// week doesn't include today.
function todayIndex() {
  const days = dietStore.activePlan?.day_plans
  if (!days) return 0
  const sorted = [...days].sort((a, b) => new Date(a.fecha) - new Date(b.fecha))
  const today = new Date().toDateString()
  const i = sorted.findIndex(d => new Date(d.fecha).toDateString() === today)
  return i >= 0 ? i : 0
}

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
  selectDay(todayIndex())
}

function handlePlanFailed() {
  // handled visually inside poller
}

function isTrainingDay(dateStr) {
  if (!dateStr || !userStore.user) return false
  // Parse the full ISO timestamp so the browser maps it back to the intended
  // local calendar day (the backend serialises dates with a tz offset).
  const day = new Date(dateStr).getDay() // 0 = Sunday
  const isoDay = day === 0 ? 7 : day

  // Prefer the user's explicit training days; fall back to the activity preset.
  const dias = userStore.user.dias_entreno
  if (Array.isArray(dias)) return dias.includes(isoDay)

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

const daysNavRef = ref(null)

function selectDay(index) {
  activeDayIndex.value = index
  // Scroll the day strip so the selected day is centred (reveals adjacent days).
  nextTick(() => {
    const nav = daysNavRef.value
    const el = nav?.children?.[index]
    if (!nav || !el) return
    const navRect = nav.getBoundingClientRect()
    const elRect = el.getBoundingClientRect()
    const delta = (elRect.left - navRect.left) - (nav.clientWidth - el.clientWidth) / 2
    nav.scrollTo({ left: nav.scrollLeft + delta, behavior: 'smooth' })
  })
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  // Parse the full ISO timestamp so it maps to the intended local calendar day.
  const formatted = new Date(dateStr).toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' })
  return formatted.charAt(0).toUpperCase() + formatted.slice(1)
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

    <!-- ── Plan Generating Poller State ────────────────── -->
    <div v-else-if="dietStore.planStatus === 'pending' || dietStore.isGenerating" class="poller-wrapper">
      <PlanStatusPoller
        :planId="currentPlanId || dietStore.activePlan?.id"
        @plan-ready="handlePlanReady"
        @plan-failed="handlePlanFailed"
      />
    </div>

    <!-- ── Loading Active Plan State ───────────────────── -->
    <div v-else-if="!dietStore.hasLoaded" class="card-empty-state">
      <div class="plan-spinner" aria-hidden="true"></div>
      <p class="empty-desc">Cargando tu plan…</p>
    </div>

    <!-- ── No Active Plan State ────────────────────────── -->
    <div v-else-if="!dietStore.activePlan" class="card-empty-state">
      <div class="empty-icon"><Apple :size="56" :stroke-width="1.75" aria-hidden="true" /></div>
      <h2 class="empty-title">Genera tu primer plan semanal</h2>
      <p class="empty-desc">
        ¡Tu perfil está listo! Haz clic a continuación para generar tu plan de alimentación determinista completamente personalizado para 7 días.
      </p>
      <motion.button :while-press="tap" class="btn-cta" @click="handleGenerate">Generar mi plan</motion.button>
    </div>

    <!-- ── Active Weekly Plan State ────────────────────── -->
    <div v-else class="plan-content">
      
      <!-- Header / General Info -->
      <header class="plan-header">
        <h1 class="plan-title">Tu semana</h1>
        <motion.button :while-press="dietStore.isGenerating ? undefined : tapSubtle" class="btn-regenerate" @click="handleGenerate" :disabled="dietStore.isGenerating">
          Regenerar plan
        </motion.button>
      </header>

      <!-- Days Navigation Tabs -->
      <nav class="days-nav" aria-label="Días de la semana" ref="daysNavRef">
        <motion.button
          v-for="(day, index) in daysOfWeek"
          :key="day"
          :while-press="tapSubtle"
          class="day-tab"
          :class="{ 'day-tab--active': activeDayIndex === index }"
          @click="selectDay(index)"
        >
          <span class="day-name">{{ day }}</span>
        </motion.button>
      </nav>

      <!-- Active Day Details -->
      <AnimatePresence mode="wait">
      <motion.main
        v-if="currentDayPlan"
        :key="activeDayIndex"
        class="day-details"
        :variants="page"
        initial="hidden"
        animate="show"
        exit="exit"
      >

        <!-- Day summary card -->
        <motion.div layout class="day-summary-card" :transition="spring.gentle">
          <div class="summary-info">
            <span class="summary-date">{{ formatDate(currentDayPlan.fecha) }}</span>
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
              <span class="stat-num"><AnimatedNumber :value="Math.round(currentDayPlan.calorias_objetivo)" /></span>
              <span class="stat-unit">kcal</span>
            </div>
            <div class="day-macro-stat font-protein">
              <span class="stat-num"><AnimatedNumber :value="Math.round(currentDayPlan.proteina_obj)" /></span>
              <span class="stat-unit">g prot</span>
            </div>
            <div class="day-macro-stat font-carbs">
              <span class="stat-num"><AnimatedNumber :value="Math.round(currentDayPlan.carbos_obj)" /></span>
              <span class="stat-unit">g carb</span>
            </div>
            <div class="day-macro-stat font-fat">
              <span class="stat-num"><AnimatedNumber :value="Math.round(currentDayPlan.grasa_obj)" /></span>
              <span class="stat-unit">g grasa</span>
            </div>
          </div>
        </motion.div>

        <!-- Meals List -->
        <section class="meals-list">
          <article 
            v-for="meal in currentDayPlan.meals" 
            :key="meal.id"
            class="meal-card"
          >
            <div class="meal-header">
              <div class="meal-title-col">
                <h3 class="meal-name">{{ meal.nombre }}</h3>
                <span class="meal-time">{{ formatTime(meal.hora_objetivo) }}</span>
                <motion.button
                  :while-press="regeneratingMealId === meal.id ? undefined : tapSubtle"
                  class="btn-reroll"
                  :class="{ 'btn-reroll--loading': regeneratingMealId === meal.id }"
                  :disabled="regeneratingMealId === meal.id"
                  @click="handleRegenerateMeal(meal.id)"
                  title="Generar otra opción de plato"
                >
                  <RefreshCw :size="14" :stroke-width="2" aria-hidden="true" />
                  {{ regeneratingMealId === meal.id ? 'Generando…' : 'Otra opción' }}
                </motion.button>
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
                <div class="item-row">
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
                </div>
              </li>
            </ul>
          </article>
        </section>
      </motion.main>
      </AnimatePresence>

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
  max-width: 1100px;
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

.plan-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(255, 212, 0, 0.2);
  border-top-color: var(--color-accent);
  border-radius: var(--radius-full);
  margin: 0 auto var(--space-5);
  animation: plan-spin 0.8s linear infinite;
}

@keyframes plan-spin {
  to { transform: rotate(360deg); }
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
  background: rgba(255, 212, 0, 0.15);
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
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-5);
  align-items: start;
}

/* On wider screens, lay the meal cards out in two columns. */
@media (min-width: 768px) {
  .meals-list {
    grid-template-columns: repeat(2, 1fr);
  }
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

.meal-title-col {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-1);
}

.btn-reroll {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  margin-top: var(--space-1);
  padding: var(--space-1) var(--space-2);
  background: var(--color-bg);
  border: 1px solid var(--border-default);
  color: var(--color-system);
  font-size: var(--fs-xs);
  font-weight: 600;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-reroll:hover:not(:disabled) {
  background-color: rgba(37, 99, 235, 0.08);
  border-color: var(--color-system);
}

.btn-reroll--loading {
  opacity: 0.6;
  cursor: progress;
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
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--border-subtle);
}

.meal-item:last-child {
  border-bottom: none;
}

.item-info {
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
}

/* Second line: macros on the left, substitute button on the right */
.item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-3);
}

.btn-substitute {
  flex: 0 0 auto;
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
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2) var(--space-3);
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
