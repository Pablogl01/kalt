<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { TrendingUp, Scale, TrendingDown } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/userStore'
import { useDietStore } from '@/stores/dietStore'
import api from '@/api/client'
import PlanStatusPoller from '@/components/PlanStatusPoller.vue'

const router = useRouter()
const userStore = useUserStore()
const dietStore = useDietStore()

// State
const currentStep = ref(1)
const step1Macros = ref(null)
const generatingPlanId = ref(null)
const foodsList = ref([])
const searchFoodQuery = ref('')
const selectedFoodId = ref('')
const restrictionType = ref('alergia')
const restrictions = ref([]) // array of { food_id, name, tipo }

// Form data
const formStep1 = reactive({
  sexo: '',
  peso: '',
  altura: '',
  edad: '',
  objetivo: 'mantenimiento',
})

const formStep2 = reactive({
  nivel_actividad: 'moderado',
  dias_entreno: [1, 3, 5], // default Mon, Wed, Fri
  num_comidas: 4,
  hora_gimnasio: '18:00',
})

const loading = ref(false)
const errorMsg = ref('')

const daysOfWeek = [
  { value: 1, label: 'L' },
  { value: 2, label: 'M' },
  { value: 3, label: 'X' },
  { value: 4, label: 'J' },
  { value: 5, label: 'V' },
  { value: 6, label: 'S' },
  { value: 7, label: 'D' },
]

const activityOptions = [
  { value: 'sedentario', label: 'Sedentario', desc: 'Sin ejercicio o muy poco' },
  { value: 'ligero', label: 'Ligero', desc: 'Ejercicio ligero (1-2 días/semana)' },
  { value: 'moderado', label: 'Moderado', desc: 'Ejercicio moderado (3-5 días/semana)' },
  { value: 'alto', label: 'Alto', desc: 'Ejercicio intenso (6-7 días/semana)' },
]

const goalOptions = [
  { value: 'volumen', icon: TrendingUp, label: 'Volumen', desc: 'Ganar masa muscular (+300 kcal)' },
  { value: 'mantenimiento', icon: Scale, label: 'Mantenimiento', desc: 'Mantener peso (TDEE)' },
  { value: 'definicion', icon: TrendingDown, label: 'Definición', desc: 'Perder grasa (−300 kcal)' },
]

// Fetch foods for step 3
onMounted(async () => {
  try {
    const { data } = await api.get('/foods')
    foodsList.value = data
  } catch (err) {
    console.error('Error fetching foods list:', err)
  }

  // Prepopulate if user exists
  if (userStore.user) {
    formStep1.sexo = userStore.user.sexo || ''
    formStep1.peso = userStore.user.peso || ''
    formStep1.altura = userStore.user.altura || ''
    formStep1.edad = userStore.user.edad || ''
    formStep1.objetivo = userStore.user.objetivo || 'mantenimiento'
    formStep2.nivel_actividad = userStore.user.nivel_actividad || 'moderado'
  }
})

// Watch step 1 fields to update macros in real-time
const isStep1Complete = computed(() => {
  const p = parseFloat(formStep1.peso)
  const h = parseFloat(formStep1.altura)
  const e = parseInt(formStep1.edad)
  return formStep1.sexo && 
         p >= 20 && p <= 500 && 
         h >= 100 && h <= 250 && 
         e >= 10 && e <= 120 && 
         formStep1.objetivo
})

watch(
  () => ({ ...formStep1 }),
  async (newVal) => {
    if (isStep1Complete.value) {
      try {
        const { data } = await api.post('/onboarding/step1', newVal)
        step1Macros.value = data.macros
      } catch (err) {
        console.warn('Real-time macro calc failed', err)
      }
    } else {
      step1Macros.value = null
    }
  },
  { deep: true, immediate: true }
)

// Actions
async function submitStep1() {
  if (!isStep1Complete.value) return
  loading.value = true
  errorMsg.value = ''
  try {
    const { data } = await api.post('/onboarding/step1', formStep1)
    await userStore.fetchProfile() // Update global store
    currentStep.value = 2
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Error al guardar los datos.'
  } finally {
    loading.value = false
  }
}

async function submitStep2() {
  loading.value = true
  errorMsg.value = ''
  try {
    const { data } = await api.post('/onboarding/step2', {
      nivel_actividad: formStep2.nivel_actividad,
      dias_entreno: formStep2.dias_entreno,
      num_comidas: parseInt(formStep2.num_comidas),
      hora_gimnasio: formStep2.hora_gimnasio + ':00',
    })
    generatingPlanId.value = data.weekly_plan_id
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Error al guardar la personalización.'
    loading.value = false
  }
}

function handlePlanReady() {
  loading.value = false
  currentStep.value = 3
}

function handlePlanFailed(msg) {
  loading.value = false
  errorMsg.value = msg || 'La generación del plan falló. Por favor, reinténtalo.'
  generatingPlanId.value = null
}

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

function addRestriction() {
  if (!selectedFoodId.value) return
  const food = foodsList.value.find(f => f.id === selectedFoodId.value)
  if (!food) return

  // Prevent duplicates
  if (restrictions.value.some(r => r.food_id === food.id)) return

  restrictions.value.push({
    food_id: food.id,
    name: food.nombre,
    tipo: restrictionType.value
  })

  // Clear fields
  selectedFoodId.value = ''
  searchFoodQuery.value = ''
}

function removeRestriction(index) {
  restrictions.value.splice(index, 1)
}

async function finishOnboarding() {
  loading.value = true
  errorMsg.value = ''
  try {
    await api.post('/onboarding/step3', {
      restricciones: restrictions.value.map(r => ({
        food_id: r.food_id,
        tipo: r.tipo
      }))
    })
    await userStore.fetchProfile() // Sync profiles & macros
    await dietStore.loadActivePlan() // Load newly generated plan
    router.push({ name: 'dashboard' })
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Error al guardar restricciones.'
    loading.value = false
  }
}

const formatRestrictionType = (tipo) => {
  const map = {
    alergia: 'Alergia',
    intolerancia: 'Intolerancia',
    no_me_gusta: 'No me gusta',
  }
  return map[tipo] || tipo
}

function skipStep3() {
  userStore.fetchProfile().then(() => {
    dietStore.loadActivePlan().then(() => {
      router.push({ name: 'dashboard' })
    })
  })
}

function toggleTrainingDay(day) {
  const index = formStep2.dias_entreno.indexOf(day)
  if (index === -1) {
    formStep2.dias_entreno.push(day)
  } else {
    formStep2.dias_entreno.splice(index, 1)
  }
}
</script>

<template>
  <div class="onboarding-container">
    <!-- Progress Indicator -->
    <div class="steps-progress">
      <div 
        v-for="step in [1, 2, 3]" 
        :key="step" 
        class="step-indicator"
        :class="{ 
          'step-indicator--active': currentStep === step,
          'step-indicator--completed': currentStep > step 
        }"
      >
        <span class="step-num">{{ step }}</span>
        <span class="step-name">
          {{ step === 1 ? 'Mínimo vital' : step === 2 ? 'Personalización' : 'Restricciones' }}
        </span>
      </div>
    </div>

    <!-- MAIN FORM BODY -->
    <div class="onboarding-card">
      <div v-if="errorMsg" class="error-banner" role="alert">
        {{ errorMsg }}
      </div>

      <!-- STEP 1 -->
      <div v-if="currentStep === 1" class="step-view">
        <h2 class="step-title">Cuéntanos sobre ti</h2>
        <p class="step-subtitle">Ingresa tus datos vitales mínimos para empezar a calcular tus necesidades energéticas.</p>

        <form @submit.prevent="submitStep1" class="onboarding-form">
          <div class="form-row">
            <div class="form-group">
              <label for="step1-sexo">Sexo</label>
              <select id="step1-sexo" v-model="formStep1.sexo" class="form-select" required>
                <option value="" disabled>Selecciona...</option>
                <option value="hombre">Hombre</option>
                <option value="mujer">Mujer</option>
              </select>
            </div>

            <div class="form-group">
              <label for="step1-edad">Edad</label>
              <input type="number" id="step1-edad" v-model.number="formStep1.edad" min="10" max="120" placeholder="Años" class="form-input" required />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="step1-peso">Peso (kg)</label>
              <input type="number" id="step1-peso" v-model.number="formStep1.peso" min="20" max="500" step="0.1" placeholder="Ej: 75.5" class="form-input" required />
            </div>

            <div class="form-group">
              <label for="step1-altura">Altura (cm)</label>
              <input type="number" id="step1-altura" v-model.number="formStep1.altura" min="100" max="250" placeholder="Ej: 178" class="form-input" required />
            </div>
          </div>

          <div class="form-group">
            <label>Objetivo Deportivo</label>
            <div class="goal-grid">
              <label 
                v-for="opt in goalOptions" 
                :key="opt.value" 
                class="goal-card"
                :class="{ 'goal-card--active': formStep1.objetivo === opt.value }"
              >
                <input type="radio" :value="opt.value" v-model="formStep1.objetivo" class="goal-radio-hidden" />
                <span class="goal-icon"><component :is="opt.icon" :size="28" :stroke-width="2" aria-hidden="true" /></span>
                <span class="goal-title">{{ opt.label }}</span>
                <span class="goal-desc">{{ opt.desc }}</span>
              </label>
            </div>
          </div>

          <!-- Real-time computed macros preview -->
          <div v-if="step1Macros" class="macros-preview">
            <h4 class="preview-title">Tu objetivo diario estimado</h4>
            <div class="preview-grid">
              <div class="p-macro text-calories">
                <span class="p-val">{{ Math.round(step1Macros.calorias) }}</span>
                <span class="p-lbl">kcal</span>
              </div>
              <div class="p-macro text-protein">
                <span class="p-val">{{ Math.round(step1Macros.proteina) }}g</span>
                <span class="p-lbl">Proteína</span>
              </div>
              <div class="p-macro text-carbs">
                <span class="p-val">{{ Math.round(step1Macros.carbos) }}g</span>
                <span class="p-lbl">Carbohidratos</span>
              </div>
              <div class="p-macro text-fat">
                <span class="p-val">{{ Math.round(step1Macros.grasa) }}g</span>
                <span class="p-lbl">Grasa</span>
              </div>
            </div>
          </div>

          <button type="submit" class="btn-submit" :disabled="loading || !isStep1Complete">
            {{ loading ? 'Guardando...' : 'Continuar a personalización' }}
          </button>
        </form>
      </div>

      <!-- STEP 2 -->
      <div v-else-if="currentStep === 2" class="step-view">
        <div v-if="generatingPlanId">
          <PlanStatusPoller :planId="generatingPlanId" @plan-ready="handlePlanReady" @plan-failed="handlePlanFailed" />
        </div>

        <div v-else>
          <h2 class="step-title">Personaliza tu rutina</h2>
          <p class="step-subtitle">Configura tus hábitos semanales de entrenamiento y comidas para estructurar tu plan.</p>

          <form @submit.prevent="submitStep2" class="onboarding-form">
            <div class="form-group">
              <label for="step2-actividad">Nivel de actividad</label>
              <select id="step2-actividad" v-model="formStep2.nivel_actividad" class="form-select">
                <option v-for="opt in activityOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }} — {{ opt.desc }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>Días que entrenas</label>
              <div class="days-selector">
                <button 
                  type="button"
                  v-for="day in daysOfWeek" 
                  :key="day.value" 
                  class="day-btn"
                  :class="{ 'day-btn--active': formStep2.dias_entreno.includes(day.value) }"
                  @click="toggleTrainingDay(day.value)"
                >
                  {{ day.label }}
                </button>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="step2-comidas">Número de comidas al día</label>
                <select id="step2-comidas" v-model.number="formStep2.num_comidas" class="form-select">
                  <option v-for="n in [3, 4, 5, 6]" :key="n" :value="n">{{ n }} comidas</option>
                </select>
              </div>

              <div class="form-group">
                <label for="step2-gym-hour">Hora de entreno (gimnasio)</label>
                <input type="time" id="step2-gym-hour" v-model="formStep2.hora_gimnasio" class="form-input" />
              </div>
            </div>

            <button type="submit" class="btn-submit" :disabled="loading">
              {{ loading ? 'Generando plan...' : 'Generar mi plan semanal' }}
            </button>
          </form>
        </div>
      </div>

      <!-- STEP 3 -->
      <div v-else-if="currentStep === 3" class="step-view">
        <h2 class="step-title">Restricciones alimentarias</h2>
        <p class="step-subtitle">Opcional. Añade alergias o intolerancias para que la app evite estos ingredientes en tu dieta.</p>

        <div class="restriction-box">
          <div class="search-wrap">
            <input 
              type="text" 
              v-model="searchFoodQuery" 
              @focus="showDropdown = true"
              placeholder="Buscar alimento (ej: huevo, trigo, cacahuete...)" 
              class="form-input" 
            />
            <div v-if="showDropdown && filteredFoods.length > 0" class="search-dropdown">
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

          <div class="restriction-type-select">
            <select v-model="restrictionType" class="form-select">
              <option value="alergia">Alergia (Exclusión total)</option>
              <option value="intolerancia">Intolerancia (Evitar por defecto)</option>
              <option value="no_me_gusta">No me gusta (Sustituir)</option>
            </select>
            <button type="button" @click="addRestriction" class="btn-add-restriction" :disabled="!selectedFoodId">
              Añadir
            </button>
          </div>
        </div>

        <!-- Current restrictions list -->
        <div v-if="restrictions.length > 0" class="added-restrictions">
          <h4 class="list-title">Restricciones añadidas:</h4>
          <ul class="restrictions-list">
            <li v-for="(rest, index) in restrictions" :key="index" class="restriction-item">
              <span class="rest-food-name">{{ rest.name }}</span>
              <span class="rest-type-tag" :class="`rest-type-tag--${rest.tipo}`">{{ formatRestrictionType(rest.tipo) }}</span>
              <button type="button" @click="removeRestriction(index)" class="btn-remove">×</button>
            </li>
          </ul>
        </div>

        <div class="step3-actions">
          <button type="button" @click="skipStep3" class="btn-skip" :disabled="loading">
            Saltar este paso
          </button>
          <button type="button" @click="finishOnboarding" class="btn-submit" :disabled="loading">
            {{ loading ? 'Guardando...' : 'Finalizar y guardar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.onboarding-container {
  max-width: 600px;
  margin: 2rem auto 4rem;
  padding: 0 1rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

/* Progress bar indicators */
.steps-progress {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  background: var(--color-surface);
  border-radius: 12px;
  padding: 1rem;
  border: 1px solid rgba(138, 129, 120, 0.12);
}

.step-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  opacity: 0.5;
  transition: opacity 0.3s;
}

.step-indicator--active {
  opacity: 1;
  font-weight: 700;
}

.step-indicator--completed {
  opacity: 0.8;
}

.step-num {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--color-text-muted);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
}

.step-indicator--active .step-num {
  background: var(--color-accent);
  color: var(--color-text);
}

.step-indicator--completed .step-num {
  background: var(--color-protein);
  color: white;
}

.step-name {
  font-size: 0.875rem;
  color: var(--color-text);
}

/* Card layout */
.onboarding-card {
  background: var(--color-surface);
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 4px 24px rgba(31, 27, 22, 0.05);
  border: 1px solid rgba(138, 129, 120, 0.12);
}

.step-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 0.5rem;
}

.step-subtitle {
  color: var(--color-text-muted);
  font-size: 0.9375rem;
  margin: 0 0 2rem;
  line-height: 1.5;
}

/* Form inputs */
.onboarding-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
}

.form-input, .form-select {
  padding: 0.75rem 1rem;
  border-radius: 10px;
  border: 1px solid rgba(138, 129, 120, 0.25);
  background: var(--color-surface);
  font-family: inherit;
  font-size: 0.9375rem;
  color: var(--color-text);
  outline: none;
  transition: border-color 0.2s;
  width: 100%;
}

.form-input:focus, .form-select:focus {
  border-color: var(--color-accent);
}

/* Goal selector grid */
.goal-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}

@media (min-width: 480px) {
  .goal-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.goal-card {
  border: 1px solid rgba(138, 129, 120, 0.2);
  border-radius: 12px;
  padding: 1rem 0.75rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: var(--color-surface);
}

.goal-card:hover {
  border-color: var(--color-accent);
  background: rgba(168, 224, 99, 0.02);
}

.goal-card--active {
  border-color: var(--color-accent);
  background: rgba(168, 224, 99, 0.05);
  box-shadow: 0 0 0 1px var(--color-accent);
}

.goal-radio-hidden {
  display: none;
}

.goal-icon {
  display: inline-flex;
  color: var(--color-text-muted);
  margin-bottom: 0.5rem;
}

.goal-card--active .goal-icon {
  color: var(--color-accent-dark);
}

.goal-title {
  font-weight: 700;
  font-size: 0.875rem;
  color: var(--color-text);
  margin-bottom: 0.25rem;
}

.goal-desc {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

/* Real-time calculated macros preview */
.macros-preview {
  background: var(--color-bg);
  border-radius: 14px;
  padding: 1.25rem;
  border: 1px solid rgba(138, 129, 120, 0.12);
}

.preview-title {
  font-size: 0.8125rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-muted);
  font-weight: 700;
  margin: 0 0 1rem;
}

.preview-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.75rem;
}

.p-macro {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.75rem 0.25rem;
  background: var(--color-surface);
  border-radius: 10px;
  border: 1px solid rgba(138, 129, 120, 0.08);
}

.p-val {
  font-size: 1.25rem;
  font-weight: 700;
}

.p-lbl {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
  margin-top: 0.125rem;
  font-weight: 500;
}

.text-calories { color: var(--color-calories); }
.text-protein { color: var(--color-protein); }
.text-carbs { color: var(--color-carbs); }
.text-fat { color: var(--color-fat); }

/* Day Selector Buttons */
.days-selector {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.day-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 1px solid rgba(138, 129, 120, 0.25);
  background: var(--color-surface);
  color: var(--color-text);
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.day-btn:hover {
  border-color: var(--color-accent);
}

.day-btn--active {
  background: var(--color-accent);
  border-color: var(--color-accent);
  color: var(--color-text);
  font-weight: 700;
}

/* Restrictions styling */
.restriction-box {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.search-wrap {
  position: relative;
}

.search-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: var(--color-surface);
  border: 1px solid rgba(138, 129, 120, 0.2);
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  z-index: 10;
  overflow: hidden;
}

.dropdown-item {
  padding: 0.75rem 1rem;
  cursor: pointer;
  font-size: 0.875rem;
}

.dropdown-item:hover {
  background: rgba(168, 224, 99, 0.08);
}

.restriction-type-select {
  display: flex;
  gap: 0.75rem;
}

.btn-add-restriction {
  padding: 0.75rem 1.25rem;
  background: var(--color-text);
  color: white;
  border: none;
  font-weight: 600;
  border-radius: 10px;
  cursor: pointer;
}

.btn-add-restriction:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.added-restrictions {
  background: var(--color-bg);
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 2rem;
  border: 1px solid rgba(138, 129, 120, 0.08);
}

.list-title {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text-muted);
  margin: 0 0 0.75rem;
}

.restrictions-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.restriction-item {
  display: flex;
  align-items: center;
  background: var(--color-surface);
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  border: 1px solid rgba(138, 129, 120, 0.1);
  font-size: 0.875rem;
}

.rest-type-tag {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
  margin-left: auto;
  margin-right: 1rem;
  text-transform: uppercase;
}

.rest-type-tag--alergia { background-color: rgba(239, 68, 68, 0.1); color: #EF4444; }
.rest-type-tag--intolerancia { background-color: rgba(245, 158, 11, 0.1); color: #F59E0B; }
.rest-type-tag--no_me_gusta { background-color: rgba(107, 114, 128, 0.1); color: #6B7280; }

.btn-remove {
  background: none;
  border: none;
  color: var(--color-text-muted);
  font-size: 1.25rem;
  line-height: 1;
  cursor: pointer;
}

.btn-remove:hover {
  color: #EF4444;
}

.step3-actions {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

.btn-skip {
  background: transparent;
  border: 1px solid rgba(138, 129, 120, 0.3);
  color: var(--color-text-muted);
  padding: 0.75rem 1.25rem;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
}

.btn-skip:hover {
  background: rgba(138, 129, 120, 0.05);
  color: var(--color-text);
}

/* Submit button */
.btn-submit {
  width: 100%;
  padding: 0.875rem;
  background: var(--color-accent);
  color: var(--color-text);
  border: none;
  border-radius: 10px;
  font-size: 0.9375rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s;
  text-align: center;
}

.btn-submit:hover {
  background: var(--color-accent-dark);
}

.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.error-banner {
  background-color: rgba(239, 68, 68, 0.1);
  color: #EF4444;
  padding: 0.75rem 1rem;
  border-radius: 10px;
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
  border: 1px solid rgba(239, 68, 68, 0.2);
}
</style>
