<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Activity, Check } from 'lucide-vue-next'
import { useLogStore } from '@/stores/logStore'
import { useUserStore } from '@/stores/userStore'
import api from '@/api/client'
import MacroBar from '@/components/MacroBar.vue'
import MealCard from '@/components/MealCard.vue'
import RecalcNotice from '@/components/RecalcNotice.vue'
import SubstituteSelector from '@/components/SubstituteSelector.vue'

const logStore = useLogStore()
const userStore = useUserStore()

// State
const currentDate = ref(new Date())
const foodsList = ref([])
const showExtraForm = ref(false)

const activeNoticeMessage = ref('')
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
  const prev = new Date(currentDate.value)
  prev.setDate(prev.getDate() - 1)
  currentDate.value = prev
}

function navigateNext() {
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
}

function triggerSubstitute(item) {
  selectedMealItem.value = item
  showSubstituteSelector.value = true
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
      <button @click="navigatePrevious" class="btn-nav">
        ←
      </button>
      <div class="date-info">
        <h1 class="date-title">{{ formattedDateDisplay }}</h1>
        <span v-if="isToday" class="badge-today">Hoy</span>
      </div>
      <button @click="navigateNext" :disabled="disableNext" class="btn-nav" :class="{ 'btn-nav--disabled': disableNext }">
        →
      </button>
    </header>

    <div v-if="logStore.dailyLog" class="log-content-wrap">
      
      <!-- ── Recalculation Notice ─────────────────────────── -->
      <RecalcNotice 
        v-if="recalcMessage" 
        :message="recalcMessage" 
        @close="activeNoticeMessage = ''; if (logStore.dailyLog) { logStore.dailyLog.recalculo_motivo = null; }"
      />

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
            <label v-if="logStore.dailyLog.entreno_planificado" class="checkbox-container">
              <input 
                type="checkbox" 
                :checked="logStore.dailyLog.ha_entrenado" 
                @change="handleTrainingToggle" 
              />
              <span class="checkbox-label">He entrenado hoy</span>
            </label>
            <button 
              v-else 
              @click="handleTrainingBtnClick" 
              class="btn-train"
              :class="{ 'btn-train--active': logStore.dailyLog.ha_entrenado }"
            >
              <Check v-if="logStore.dailyLog.ha_entrenado" :size="15" :stroke-width="2.5" aria-hidden="true" />
              {{ logStore.dailyLog.ha_entrenado ? 'Entrenado' : 'He entrenado hoy' }}
            </button>
          </div>
        </div>
      </section>

      <!-- ── Meals list ──────────────────────────────────── -->
      <section class="meals-section">
        <h2 class="section-subtitle">Comidas del día</h2>
        
        <div class="meals-list">
          <MealCard 
            v-for="ml in logStore.mealLogs" 
            :key="ml.id" 
            :mealLog="ml"
            @complete="handleComplete"
            @skip="handleSkip"
            @reset="handleReset"
            @substitute="triggerSubstitute"
          />

          <!-- Empty state -->
          <div v-if="logStore.mealLogs.length === 0" class="empty-meals">
            <p>No tienes comidas planificadas para hoy.</p>
          </div>
        </div>

        <!-- Extra meal inline form -->
        <div v-if="showExtraForm" class="extra-meal-form-card">
          <div class="form-header">
            <h4>Añadir comida extra</h4>
            <button @click="showExtraForm = false" class="btn-close">×</button>
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
        </div>

        <!-- Add extra meal CTA -->
        <button 
          v-if="!showExtraForm" 
          @click="showExtraForm = true" 
          class="btn-add-extra-meal"
        >
          + Añadir comida extra
        </button>
      </section>

    </div>

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
  max-width: 600px;
  margin: 0 auto;
  padding: 1.5rem 1rem 4rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Date Navigator styling */
.date-navigator {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--color-surface);
  border: 1px solid rgba(138, 129, 120, 0.12);
  border-radius: 16px;
  padding: 0.875rem 1.25rem;
}

.btn-nav {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
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
  gap: 0.5rem;
}

.date-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.badge-today {
  background-color: var(--color-accent);
  color: var(--color-text);
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  text-transform: uppercase;
}

.log-content-wrap {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Generic Card Layout */
.section-card {
  background: var(--color-surface);
  border: 1px solid rgba(138, 129, 120, 0.12);
  border-radius: 16px;
  padding: 1.25rem;
}

.section-subtitle {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 1rem;
}

/* Training card */
.training-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.training-info {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.card-icon {
  display: inline-flex;
  align-items: center;
  color: var(--color-text-muted);
}

.training-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
}

.training-desc {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  margin: 0;
}

.checkbox-container {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-train {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 0.5rem 1rem;
  border-radius: 10px;
  border: 1px solid rgba(138, 129, 120, 0.25);
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

/* Meals Section Layout */
.meals-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.meals-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.empty-meals {
  text-align: center;
  padding: 2.5rem 1rem;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  background: var(--color-surface);
  border: 1px dashed rgba(138, 129, 120, 0.3);
  border-radius: 16px;
}

.btn-add-extra-meal {
  width: 100%;
  padding: 0.875rem;
  background: transparent;
  border: 1px dashed rgba(138, 129, 120, 0.3);
  color: var(--color-text);
  font-weight: 600;
  border-radius: 16px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.9375rem;
}

.btn-add-extra-meal:hover {
  background-color: rgba(138, 129, 120, 0.03);
  border-color: var(--color-accent);
}

/* Extra meal card editor */
.extra-meal-form-card {
  background: var(--color-surface);
  border: 1px solid rgba(37, 99, 235, 0.25);
  border-radius: 16px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  box-shadow: 0 4px 16px rgba(37, 99, 235, 0.04);
}

.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-header h4 {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: var(--color-text-muted);
  cursor: pointer;
}

.extra-items-inputs {
  display: grid;
  grid-template-columns: 1fr 90px 70px;
  gap: 0.5rem;
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
  border: 1px solid rgba(138, 129, 120, 0.25);
  border-radius: 8px;
  z-index: 10;
  max-height: 150px;
  overflow-y: auto;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.dropdown-item {
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  font-size: 0.8125rem;
}

.dropdown-item:hover {
  background-color: rgba(168, 224, 99, 0.08);
}

.weight-input-wrap {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.unit {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.text-right {
  text-align: right;
}

.form-input {
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  border: 1px solid rgba(138, 129, 120, 0.25);
  font-size: 0.875rem;
  outline: none;
  width: 100%;
}

.form-input:focus {
  border-color: var(--color-accent);
}

.btn-add {
  padding: 0.5rem;
  background: var(--color-text);
  color: white;
  border: none;
  font-weight: 600;
  border-radius: 8px;
  font-size: 0.8125rem;
  cursor: pointer;
}

.btn-add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.added-extra-items {
  border-top: 1px solid rgba(138, 129, 120, 0.1);
  padding-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.extra-items-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.extra-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8125rem;
  background-color: var(--color-bg);
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
}

.extra-item-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-remove-item {
  background: none;
  border: none;
  font-size: 1.125rem;
  color: var(--color-text-muted);
  cursor: pointer;
  line-height: 1;
}

.btn-remove-item:hover {
  color: #EF4444;
}

.btn-save-extra {
  width: 100%;
  padding: 0.625rem;
  background: var(--color-accent);
  color: var(--color-text);
  border: none;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.875rem;
  cursor: pointer;
}

.btn-save-extra:hover {
  background: var(--color-accent-dark);
}
</style>
