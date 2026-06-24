<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { motion, AnimatePresence } from 'motion-v'
import { Activity, Check } from 'lucide-vue-next'
import { useLogStore } from '@/stores/logStore'
import { useUserStore } from '@/stores/userStore'
import api from '@/api/client'
import { spring, tap, tapSubtle, listContainer, listItem, slide, sheet } from '@/lib/motion'
import MacroBar from '@/components/MacroBar.vue'
import MealCard from '@/components/MealCard.vue'
import RecalcNotice from '@/components/RecalcNotice.vue'
import SubstituteSelector from '@/components/SubstituteSelector.vue'

// Direction of date travel (1 = next/forward, -1 = previous/back) so the
// content can slide the matching way on each change.
const slideDir = ref(1)

const logStore = useLogStore()
const userStore = useUserStore()

// State
const currentDate = ref(new Date())
const foodsList = ref([])
const showExtraForm = ref(false)

const activeNoticeMessage = ref('')
// Screen-reader announcement for meal actions (one message per action).
const liveMessage = ref('')
const showSubstituteSelector = ref(false)
const selectedMealItem = ref(null)

// Extra meal form state
const extraItems = ref([]) // array of { food_id, name, grams }
const searchFoodQuery = ref('')
const selectedFoodId = ref('')
const extraFoodGrams = ref(100)

// Load foods on mount
onMounted(async () => {
  try {
    const { data } = await api.get('/foods')
    foodsList.value = data
  } catch (err) {
    console.error('Error loading foods list:', err)
  }
})

// Format date to YYYY-MM-DD
const dateString = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = String(currentDate.value.getMonth() + 1).padStart(2, '0')
  const day = String(currentDate.value.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
})

// Format date for display (e.g. "Domingo, 14 de Junio")
const formattedDateDisplay = computed(() => {
  const options = { weekday: 'long', day: 'numeric', month: 'long' }
  const display = currentDate.value.toLocaleDateString('es-ES', options)
  return display.charAt(0).toUpperCase() + display.slice(1)
})

// Fetch daily log when date changes
watch(dateString, async (newVal) => {
  await logStore.fetchDailyLog(newVal)
}, { immediate: true })

// Date navigation actions
function navigatePrevious() {
  slideDir.value = -1
  const prev = new Date(currentDate.value)
  prev.setDate(prev.getDate() - 1)
  currentDate.value = prev
}

function navigateNext() {
  slideDir.value = 1
  const next = new Date(currentDate.value)
  next.setDate(next.getDate() + 1)

  // Do not navigate to future dates
  const today = new Date()
  today.setHours(23, 59, 59, 999)
  if (next <= today) {
    currentDate.value = next
  }
}

const isToday = computed(() => {
  const today = new Date()
  return currentDate.value.toDateString() === today.toDateString()
})

const isFutureDate = (date) => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return date >= today
}

const disableNext = computed(() => {
  const tomorrow = new Date(currentDate.value)
  tomorrow.setDate(tomorrow.getDate() + 1)
  const today = new Date()
  today.setHours(23, 59, 59, 999)
  return tomorrow > today
})

// Computed target macros
const targetMacros = computed(() => {
  if (logStore.dailyLog?.day_plan) {
    return {
      calorias: parseFloat(logStore.dailyLog.day_plan.calorias_objetivo || 0),
      proteina: parseFloat(logStore.dailyLog.day_plan.proteina_obj || 0),
      carbos: parseFloat(logStore.dailyLog.day_plan.carbos_obj || 0),
      grasa: parseFloat(logStore.dailyLog.day_plan.grasa_obj || 0),
    }
  }
  // Fallback to profile macros
  return {
    calorias: parseFloat(userStore.macros?.calorias || 2000),
    proteina: parseFloat(userStore.macros?.proteina || 150),
    carbos: parseFloat(userStore.macros?.carbos || 200),
    grasa: parseFloat(userStore.macros?.grasa || 60),
  }
})

// Computed consumed macros
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

// Handle training update
async function handleTrainingToggle(event) {
  if (!logStore.dailyLog) return
  const isChecked = event.target.checked
  const data = await logStore.updateTraining(logStore.dailyLog.id, isChecked, logStore.dailyLog.hora_gimnasio)
  if (data?.mensaje_recalculo) {
    activeNoticeMessage.value = data.mensaje_recalculo
  }
}

async function handleTrainingBtnClick() {
  if (!logStore.dailyLog) return
  const nextVal = !logStore.dailyLog.ha_entrenado
  const data = await logStore.updateTraining(logStore.dailyLog.id, nextVal, logStore.dailyLog.hora_gimnasio || '18:00:00')
  if (data?.mensaje_recalculo) {
    activeNoticeMessage.value = data.mensaje_recalculo
  }
}

async function handleComplete(mealLogId) {
  const data = await logStore.completeMeal(mealLogId)
  if (data?.mensaje_recalculo) {
    activeNoticeMessage.value = data.mensaje_recalculo
  }
  liveMessage.value = `Comida marcada como realizada. Llevas ${Math.round(consumedMacros.value.calorias)} de ${Math.round(targetMacros.value.calorias)} kilocalorías.`
}

async function handleSkip(mealLogId) {
  const data = await logStore.skipMeal(mealLogId)
  if (data?.mensaje_recalculo) {
    activeNoticeMessage.value = data.mensaje_recalculo
  }
}

async function handleReset(mealLogId) {
  await logStore.resetMeal(mealLogId)
  // Reverting clears any active recalculation, so retire its notice too.
  activeNoticeMessage.value = null
  liveMessage.value = `Comida restablecida a pendiente. Llevas ${Math.round(consumedMacros.value.calorias)} de ${Math.round(targetMacros.value.calorias)} kilocalorías.`
}

async function handleUndoRecalc() {
  if (!logStore.dailyLog?.id) return
  // Dismiss the notice instantly; the day is reverted in the background.
  activeNoticeMessage.value = ''
  try {
    await logStore.undoRecalc(logStore.dailyLog.id)
  } catch (err) {
    // Nothing to revert (no snapshot) — the notice is already dismissed.
  }
}

function triggerSubstitute(item) {
  selectedMealItem.value = item
  showSubstituteSelector.value = true
}

async function handleRegenerateMeal(mealId) {
  try {
    await api.post(`/meals/${mealId}/regenerate`)
    await logStore.fetchDailyLog(dateString.value)
  } catch (err) {
    console.error('Failed to regenerate meal:', err)
  }
}

// Supplements checklist (informational, ticks persisted per day in localStorage)
const supplements = computed(() => userStore.user?.supplements || [])
const suppChecks = ref({})
watch(dateString, (d) => {
  suppChecks.value = JSON.parse(localStorage.getItem(`supp-${d}`) || '{}')
}, { immediate: true })
function toggleSupp(id) {
  suppChecks.value[id] = !suppChecks.value[id]
  localStorage.setItem(`supp-${dateString.value}`, JSON.stringify(suppChecks.value))
}

async function handleSubstituteSelected(updatedItem) {
  await logStore.fetchDailyLog(dateString.value)
}

// Recalculation explanation mapper
const recalcMessage = computed(() => {
  if (activeNoticeMessage.value) {
    return activeNoticeMessage.value
  }
  if (!logStore.dailyLog?.recalculo_motivo) return null
  
  const motivo = logStore.dailyLog.recalculo_motivo
  if (motivo === 'no_ha_entrenado' || motivo === 'entreno_no_realizado') {
    return 'Como no has entrenado hoy, se aplicó un ajuste en tu plan reduciendo calorías de las comidas restantes.'
  }
  if (motivo === 'entreno_no_planificado' || motivo === 'entreno_realizado') {
    return 'Has entrenado en un día de descanso planificado. Se ajustaron tus macros para soportar el entrenamiento.'
  }
  if (motivo === 'comida_saltada') {
    return 'Se marcó una comida como saltada. Los macros sobrantes se han redistribuido entre tus comidas restantes.'
  }
  if (motivo === 'comida_extra') {
    return 'Has añadido una comida extra. Se ajustaron tus comidas restantes.'
  }
  return 'Tus macros diarios se han recalculado automáticamente para adaptarse a tus eventos del día.'
})

// Extra meal actions
const showDropdown = ref(false)

const filteredFoods = computed(() => {
  if (!searchFoodQuery.value) return []
  const q = searchFoodQuery.value.toLowerCase()
  return foodsList.value.filter(food => food.nombre.toLowerCase().includes(q)).slice(0, 5)
})

function selectFood(food) {
  selectedFoodId.value = food.id
  searchFoodQuery.value = food.nombre
  showDropdown.value = false
}

function addExtraItem() {
  if (!selectedFoodId.value) return
  const food = foodsList.value.find(f => f.id === selectedFoodId.value)
  if (!food) return

  extraItems.value.push({
    food_id: food.id,
    name: food.nombre,
    grams: extraFoodGrams.value
  })

  // reset form fields
  selectedFoodId.value = ''
  searchFoodQuery.value = ''
  extraFoodGrams.value = 100
}

function removeExtraItem(index) {
  extraItems.value.splice(index, 1)
}

async function saveExtraMeal() {
  if (extraItems.value.length === 0 || !logStore.dailyLog) return
  
  const data = await logStore.addExtraMeal(
    logStore.dailyLog.id, 
    extraItems.value.map(item => ({
      food_id: item.food_id,
      cantidad_gramos: item.grams
    }))
  )
  if (data?.mensaje_recalculo) {
    activeNoticeMessage.value = data.mensaje_recalculo
  }

  // Clear & hide form
  extraItems.value = []
  showExtraForm.value = false
}
</script>

<template>
  <div class="daily-log-page">
    
    <!-- ── Date Navigation ─────────────────────────────── -->
    <header class="date-navigator">
      <motion.button :while-press="tap" @click="navigatePrevious" class="btn-nav">
        ←
      </motion.button>
      <div class="date-info">
        <h1 class="date-title">{{ formattedDateDisplay }}</h1>
        <span v-if="isToday" class="badge-today">Hoy</span>
      </div>
      <motion.button :while-press="disableNext ? undefined : tap" @click="navigateNext" :disabled="disableNext" class="btn-nav" :class="{ 'btn-nav--disabled': disableNext }">
        →
      </motion.button>
    </header>

    <AnimatePresence :custom="slideDir" mode="wait">
    <motion.div
      v-if="logStore.dailyLog"
      :key="dateString"
      :custom="slideDir"
      :variants="slide"
      initial="enter"
      animate="center"
      exit="exit"
      class="log-content-wrap"
    >

      <!-- Screen-reader live announcements for meal actions -->
      <p class="sr-only" aria-live="polite">{{ liveMessage }}</p>

      <!-- ── Recalculation Notice ─────────────────────────── -->
      <AnimatePresence>
        <RecalcNotice
          v-if="recalcMessage"
          key="recalc-notice"
          :message="recalcMessage"
          @undo="handleUndoRecalc"
          @close="activeNoticeMessage = ''; if (logStore.dailyLog) { logStore.dailyLog.recalculo_motivo = null; }"
        />
      </AnimatePresence>

      <div class="log-columns">
      <div class="log-rail">
      <!-- ── Daily Macro Progress ────────────────────────── -->
      <section class="section-card macros-summary-card">
        <h2 class="section-subtitle">Progreso de hoy</h2>
        <MacroBar :macros="targetMacros" :consumed="consumedMacros" />
      </section>

      <!-- ── Training Section ────────────────────────────── -->
      <section class="section-card training-card">
        <div class="training-row">
          <div class="training-info">
            <span class="card-icon" aria-hidden="true"><Activity :size="28" :stroke-width="2" /></span>
            <div>
              <h3 class="training-title">Entrenamiento</h3>
              <p class="training-desc">
                {{ logStore.dailyLog.entreno_planificado ? 'Día de entreno planificado' : 'Día de descanso' }}
              </p>
            </div>
          </div>

          <!-- Training Checkbox or Button -->
          <div class="training-action">
            <motion.label v-if="logStore.dailyLog.entreno_planificado" :while-press="tapSubtle" class="checkbox-container">
              <input
                type="checkbox"
                :checked="logStore.dailyLog.ha_entrenado"
                @change="handleTrainingToggle"
              />
              <span class="checkbox-label">He entrenado hoy</span>
            </motion.label>
            <motion.button
              v-else
              :while-press="tap"
              @click="handleTrainingBtnClick"
              class="btn-train"
              :class="{ 'btn-train--active': logStore.dailyLog.ha_entrenado }"
            >
              <Check v-if="logStore.dailyLog.ha_entrenado" :size="15" :stroke-width="2.5" aria-hidden="true" />
              {{ logStore.dailyLog.ha_entrenado ? 'Entrenado' : 'He entrenado hoy' }}
            </motion.button>
          </div>
        </div>
      </section>

      <!-- ── Supplements checklist ───────────────────────── -->
      <section v-if="supplements.length" class="section-card supplements-card">
        <h2 class="section-subtitle">Suplementos de hoy</h2>
        <ul class="supp-check-list">
          <li v-for="s in supplements" :key="s.id">
            <label class="supp-check">
              <input type="checkbox" :checked="suppChecks[s.id]" @change="toggleSupp(s.id)" />
              <span class="supp-check-name">
                {{ s.food?.nombre }}
                <span class="supp-check-dosis">{{ Math.round(s.dosis_gramos) }}g</span>
              </span>
            </label>
          </li>
        </ul>
      </section>
      </div><!-- /log-rail -->

      <!-- ── Meals list ──────────────────────────────────── -->
      <section class="meals-section">
        <h2 class="section-subtitle">Comidas del día</h2>
        
        <motion.div class="meals-list" :variants="listContainer" initial="hidden" animate="show">
          <motion.div
            v-for="ml in logStore.mealLogs"
            :key="ml.id"
            :variants="listItem"
          >
            <MealCard
              :mealLog="ml"
              @complete="handleComplete"
              @skip="handleSkip"
              @reset="handleReset"
              @substitute="triggerSubstitute"
              @regenerate="handleRegenerateMeal"
            />
          </motion.div>

          <!-- Empty state -->
          <div v-if="logStore.mealLogs.length === 0" class="empty-meals">
            <p>No tienes comidas planificadas para hoy.</p>
          </div>
        </motion.div>

        <!-- Extra meal inline form -->
        <AnimatePresence>
        <motion.div
          v-if="showExtraForm"
          class="extra-meal-form-card"
          :variants="sheet"
          initial="hidden"
          animate="show"
          exit="exit"
        >
          <div class="form-header">
            <h4>Añadir comida extra</h4>
            <motion.button :while-press="tap" @click="showExtraForm = false" class="btn-close">×</motion.button>
          </div>
          
          <div class="extra-items-inputs">
            <div class="search-input-wrap">
              <input 
                type="text" 
                v-model="searchFoodQuery" 
                @focus="showDropdown = true"
                placeholder="Buscar alimento..." 
                class="form-input" 
              />
              <div v-if="showDropdown && filteredFoods.length > 0" class="dropdown-list">
                <div 
                  v-for="food in filteredFoods" 
                  :key="food.id" 
                  class="dropdown-item" 
                  @click="selectFood(food)"
                >
                  {{ food.nombre }}
                </div>
              </div>
            </div>

            <div class="weight-input-wrap">
              <input type="number" v-model.number="extraFoodGrams" min="1" class="form-input text-right" />
              <span class="unit">g</span>
            </div>

            <button @click="addExtraItem" class="btn-add" :disabled="!selectedFoodId">
              Añadir
            </button>
          </div>

          <!-- Added items for this extra meal -->
          <div v-if="extraItems.length > 0" class="added-extra-items">
            <ul class="extra-items-list">
              <li v-for="(item, idx) in extraItems" :key="idx" class="extra-item">
                <span>{{ item.name }}</span>
                <div class="extra-item-right">
                  <strong>{{ item.grams }}g</strong>
                  <button @click="removeExtraItem(idx)" class="btn-remove-item">×</button>
                </div>
              </li>
            </ul>
            <button @click="saveExtraMeal" class="btn-save-extra">
              Guardar comida extra
            </button>
          </div>
        </motion.div>
        </AnimatePresence>

        <!-- Add extra meal CTA -->
        <motion.button
          v-if="!showExtraForm"
          :while-press="tap"
          @click="showExtraForm = true"
          class="btn-add-extra-meal"
        >
          + Añadir comida extra
        </motion.button>
      </section>
      </div><!-- /log-columns -->

    </motion.div>
    </AnimatePresence>

    <!-- Substitute Selector Modal -->
    <SubstituteSelector
      v-if="showSubstituteSelector && selectedMealItem"
      :mealItemId="selectedMealItem.id"
      :foodName="selectedMealItem.food?.nombre"
      context="log"
      @selected="handleSubstituteSelected"
      @close="showSubstituteSelector = false; selectedMealItem = null"
    />
  </div>
</template>

<style scoped>
.daily-log-page {
  max-width: 1600px;
  margin: 0 auto;
  padding: var(--space-5) var(--space-4) var(--space-7);
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

@media (min-width: 1024px) {
  .daily-log-page {
    padding: var(--space-6) var(--space-7) var(--space-7);
  }
}

/* Two-column layout on wide screens: macros + training rail on the left,
   the meals list on the right. Single column on mobile/tablet. */
.log-columns {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.log-rail {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

@media (min-width: 1024px) {
  .log-columns {
    display: grid;
    grid-template-columns: minmax(320px, 360px) 1fr;
    gap: var(--space-5);
    align-items: start;
  }
  .log-rail {
    position: sticky;
    top: var(--space-5);
  }
}

/* Date Navigator styling */
.date-navigator {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--color-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--space-3) var(--space-4);
}

.btn-nav {
  background: transparent;
  border: none;
  font-size: var(--fs-lg);
  cursor: pointer;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  color: var(--color-text);
  transition: background 0.2s;
}

.btn-nav:hover:not(.btn-nav--disabled) {
  background-color: var(--color-bg);
}

.btn-nav--disabled {
  opacity: 0.25;
  cursor: not-allowed;
}

.date-info {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.date-title {
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: var(--color-text);
  margin: 0;
}

.badge-today {
  background-color: var(--color-accent);
  color: var(--color-text);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-pill);
  text-transform: uppercase;
}

.log-content-wrap {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

/* Generic Card Layout */
.section-card {
  background: var(--color-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  padding: var(--space-5);
}

.section-subtitle {
  font-size: var(--fs-base);
  font-weight: var(--fw-bold);
  color: var(--color-text);
  margin: 0 0 var(--space-4);
}

/* Training card */
.training-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-4);
}

.training-info {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.card-icon {
  display: inline-flex;
  align-items: center;
  color: var(--color-text-muted);
}

.training-title {
  font-size: var(--fs-base);
  font-weight: var(--fw-semibold);
  color: var(--color-text);
  margin: 0;
}

.training-desc {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
  margin: 0;
}

.checkbox-container {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  cursor: pointer;
}

.btn-train {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-strong);
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-train--active {
  background-color: var(--color-accent);
  border-color: var(--color-accent);
  color: var(--color-text);
}

/* Supplements checklist */
.supp-check-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.supp-check {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  cursor: pointer;
  font-size: var(--fs-sm);
}

.supp-check-name {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-text);
}

.supp-check-dosis {
  font-size: var(--fs-xs);
  color: var(--color-text-muted);
  font-weight: 600;
}

/* Meals Section Layout */
.meals-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.meals-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

/* Fill the wide meals column with multiple cards on large screens */
@media (min-width: 1024px) {
  .meals-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: var(--space-4);
    align-items: start;
  }
  .empty-meals {
    grid-column: 1 / -1;
  }
}

.empty-meals {
  text-align: center;
  padding: var(--space-6) var(--space-4);
  color: var(--color-text-muted);
  font-size: var(--fs-sm);
  background: var(--color-surface);
  border: 1px dashed var(--border-strong);
  border-radius: var(--radius-lg);
}

.btn-add-extra-meal {
  width: 100%;
  padding: var(--space-3);
  background: transparent;
  border: 1px dashed var(--border-strong);
  color: var(--color-text);
  font-weight: var(--fw-semibold);
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: all 0.2s;
  font-size: var(--fs-base);
}

.btn-add-extra-meal:hover {
  background-color: rgba(138, 129, 120, 0.03);
  border-color: var(--color-accent);
}

/* Extra meal card editor */
.extra-meal-form-card {
  background: var(--color-surface);
  border: 1px solid rgba(37, 99, 235, 0.25); /* semantic: extra meal = system blue */
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  box-shadow: var(--shadow-md);
}

.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-header h4 {
  margin: 0;
  font-size: var(--fs-base);
  font-weight: var(--fw-bold);
  color: var(--color-text);
}

.btn-close {
  background: none;
  border: none;
  font-size: var(--fs-lg);
  color: var(--color-text-muted);
  cursor: pointer;
}

.extra-items-inputs {
  display: grid;
  grid-template-columns: 1fr 90px 70px;
  gap: var(--space-2);
  align-items: center;
}

.search-input-wrap {
  position: relative;
}

.dropdown-list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  z-index: 10;
  max-height: 150px;
  overflow-y: auto;
  box-shadow: var(--shadow-lg);
}

.dropdown-item {
  padding: var(--space-2) var(--space-3);
  cursor: pointer;
  font-size: var(--fs-sm);
}

.dropdown-item:hover {
  background-color: rgba(255, 212, 0, 0.08);
}

.weight-input-wrap {
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.unit {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
}

.text-right {
  text-align: right;
}

.form-input {
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong);
  font-size: var(--fs-sm);
  outline: none;
  width: 100%;
}

.form-input:focus {
  border-color: var(--color-accent);
}

.btn-add {
  padding: var(--space-2);
  background: var(--color-text);
  color: white;
  border: none;
  font-weight: var(--fw-semibold);
  border-radius: var(--radius-sm);
  font-size: var(--fs-sm);
  cursor: pointer;
}

.btn-add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.added-extra-items {
  border-top: 1px solid var(--border-subtle);
  padding-top: var(--space-3);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.extra-items-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.extra-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: var(--fs-sm);
  background-color: var(--color-bg);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
}

.extra-item-right {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.btn-remove-item {
  background: none;
  border: none;
  font-size: var(--fs-lg);
  color: var(--color-text-muted);
  cursor: pointer;
  line-height: 1;
}

.btn-remove-item:hover {
  color: #EF4444;
}

.btn-save-extra {
  width: 100%;
  padding: var(--space-3);
  background: var(--color-accent);
  color: var(--color-text);
  border: none;
  border-radius: var(--radius-sm);
  font-weight: var(--fw-bold);
  font-size: var(--fs-sm);
  cursor: pointer;
}

.btn-save-extra:hover {
  background: var(--color-accent-dark);
}
</style>
