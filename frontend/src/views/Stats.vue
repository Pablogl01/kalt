<script setup>
import { ref, onMounted, watch, nextTick, computed } from 'vue'
import api from '../api/client'
import AdherenceHeatmap from '../components/AdherenceHeatmap.vue'
import WeightChart from '../components/WeightChart.vue'
import Chart from 'chart.js/auto'

// Filter Range: 7, 30, 90 days
const selectedRange = ref(30)
const ranges = [
  { label: 'Última semana', value: 7 },
  { label: 'Último mes', value: 30 },
  { label: 'Últimos 3 meses', value: 90 }
]

// Date helper
const getRangeDates = (days) => {
  const toDate = new Date()
  const fromDate = new Date()
  fromDate.setDate(toDate.getDate() - (days - 1))
  return {
    from: fromDate.toLocaleDateString('en-CA'),
    to: toDate.toLocaleDateString('en-CA')
  }
}

// Data states
const loading = ref(false)
const weightLogs = ref([])
const macroLogs = ref([])
const trainingStats = ref({
  dias_planificados: 0,
  dias_entrenados: 0,
  dias_extra: 0,
  racha_actual: 0,
  mejor_racha: 0
})

// 1-Year Heatmap Data states
const heatmapAdherence = ref([])
const heatmapMacros = ref([])

// Form state for adding weight
const showWeightModal = ref(false)
const newWeight = ref('')
const newWeightNote = ref('')
const newWeightDate = ref(new Date().toLocaleDateString('en-CA'))
const savingWeight = ref(false)
const formError = ref('')

// Chart instances
let caloriesChart = null
let macrosChart = null

const fetchRangeData = async () => {
  loading.value = true
  const { from, to } = getRangeDates(selectedRange.value)

  try {
    const [weightRes, macroRes, trainingRes] = await Promise.all([
      api.get(`/stats/weight?from=${from}&to=${to}`),
      api.get(`/stats/macros?from=${from}&to=${to}`),
      api.get(`/stats/training?from=${from}&to=${to}`)
    ])

    weightLogs.value = weightRes.data
    macroLogs.value = macroRes.data
    trainingStats.value = trainingRes.data

    nextTick(() => {
      renderCaloriesChart()
      renderMacrosChart()
    })
  } catch (error) {
    console.error('Error fetching stats data:', error)
  } finally {
    loading.value = false
  }
}

const fetchHeatmapData = async () => {
  const { from, to } = getRangeDates(365) // 1 year
  try {
    const res = await api.get(`/stats/heatmap?from=${from}&to=${to}`)
    heatmapAdherence.value = res.data.adherence
    heatmapMacros.value = res.data.macros
  } catch (error) {
    console.error('Error fetching heatmap data:', error)
  }
}

// Chart.js renderers
const renderCaloriesChart = () => {
  const ctx = document.getElementById('caloriesChartCanvas')
  if (!ctx) return

  if (caloriesChart) {
    caloriesChart.destroy()
  }

  const labels = macroLogs.value.map(log => {
    const parts = log.fecha.split('-')
    const date = new Date(parts[0], parts[1] - 1, parts[2])
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })
  })

  const realCalories = macroLogs.value.map(log => log.calorias_reales)
  const targetCalories = macroLogs.value.map(log => log.calorias_objetivo)

  caloriesChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Kcal Consumidas',
          data: realCalories,
          backgroundColor: '#2563EB', // --color-calories
          borderRadius: 4
        },
        {
          label: 'Kcal Objetivo',
          data: targetCalories,
          backgroundColor: 'rgba(100, 116, 139, 0.4)', // --color-goal
          borderRadius: 4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            color: '#1C1A17',
            font: { family: 'Plus Jakarta Sans', size: 12 }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#8A8178', font: { family: 'Plus Jakarta Sans', size: 10 } }
        },
        y: {
          grid: { color: 'rgba(138, 129, 120, 0.1)' },
          ticks: { color: '#8A8178', font: { family: 'Plus Jakarta Sans', size: 10 } }
        }
      }
    }
  })
}

const renderMacrosChart = () => {
  const ctx = document.getElementById('macrosChartCanvas')
  if (!ctx) return

  if (macrosChart) {
    macrosChart.destroy()
  }

  // Calculate average actual and average goal macros in the range
  let avgProtReal = 0, avgProtObj = 0
  let avgCarbReal = 0, avgCarbObj = 0
  let avgFatReal = 0, avgFatObj = 0
  const count = macroLogs.value.length

  if (count > 0) {
    macroLogs.value.forEach(log => {
      avgProtReal += log.proteina_real
      avgProtObj += log.proteina_objetivo
      avgCarbReal += log.carbos_real
      avgCarbObj += log.carbos_objetivo
      avgFatReal += log.grasa_real
      avgFatObj += log.grasa_objetivo
    })

    avgProtReal /= count
    avgProtObj /= count
    avgCarbReal /= count
    avgCarbObj /= count
    avgFatReal /= count
    avgFatObj /= count
  }

  macrosChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Proteína (g)', 'Carbohidratos (g)', 'Grasas (g)'],
      datasets: [
        {
          label: 'Consumo medio',
          data: [avgProtReal, avgCarbReal, avgFatReal],
          // Semantic macro colors — orange stays exclusive to carbs.
          backgroundColor: ['#16A34A', '#EA580C', '#CA8A04'],
          borderRadius: 4,
          borderWidth: 0
        },
        {
          label: 'Objetivo medio',
          data: [avgProtObj, avgCarbObj, avgFatObj],
          backgroundColor: '#64748B', // --color-goal
          borderRadius: 4,
          borderWidth: 0
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            color: '#1C1A17',
            font: { family: 'Plus Jakarta Sans', size: 12 }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(138, 129, 120, 0.1)' },
          ticks: {
            color: '#1C1A17',
            font: { family: 'Plus Jakarta Sans', size: 11 }
          }
        },
        x: {
          grid: { display: false },
          ticks: {
            color: '#1C1A17',
            font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' }
          }
        }
      }
    }
  })
}

// Add weight log
const handleAddWeight = async () => {
  formError.value = ''
  if (!newWeight.value || isNaN(newWeight.value) || newWeight.value <= 20 || newWeight.value > 500) {
    formError.value = 'El peso debe ser un número entre 20 y 500 kg.'
    return
  }

  savingWeight.value = true
  try {
    await api.post('/weight-logs', {
      peso: parseFloat(newWeight.value),
      fecha: newWeightDate.value,
      nota: newWeightNote.value || null
    })

    // Reset and close
    newWeight.value = ''
    newWeightNote.value = ''
    showWeightModal.value = false

    // Refresh range weight data and heatmap data
    await Promise.all([
      fetchRangeData(),
      fetchHeatmapData()
    ])
  } catch (error) {
    console.error('Error saving weight log:', error)
    formError.value = 'Error al registrar el peso.'
  } finally {
    savingWeight.value = false
  }
}

const selectRange = (range) => {
  selectedRange.value = range.value
}

watch(selectedRange, () => {
  fetchRangeData()
})

onMounted(() => {
  fetchRangeData()
  fetchHeatmapData()
})

// Adherence percentage
const adherencePercentage = computed(() => {
  if (macroLogs.value.length === 0) return 0
  const daysInLogs = macroLogs.value.map(l => l.fecha)
  const adherenceDays = heatmapAdherence.value.filter(h => daysInLogs.includes(h.fecha))
  const completed = adherenceDays.filter(h => h.nivel === 'completa').length
  return Math.round((completed / macroLogs.value.length) * 100) || 0
})
</script>

<template>
  <div class="stats-view">
    <div class="stats-header">
      <div>
        <h1 class="view-title">Tu Progreso</h1>
        <p class="view-subtitle">Análisis detallado de tu peso, nutrición y adherencia</p>
      </div>

      <!-- Range Selector Buttons -->
      <div class="range-selector">
        <button
          v-for="range in ranges"
          :key="range.value"
          class="range-btn"
          :class="{ active: selectedRange === range.value }"
          @click="selectRange(range)"
        >
          {{ range.label }}
        </button>
      </div>
    </div>

    <!-- KPI Grid -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-label">Adherencia Completa</div>
        <div class="kpi-value text-accent">{{ adherencePercentage }}%</div>
        <div class="kpi-desc">Días con dieta y entrenamiento cumplidos</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Entrenamientos Realizados</div>
        <div class="kpi-value">{{ trainingStats.dias_entrenados }} / {{ trainingStats.dias_planificados }}</div>
        <div class="kpi-desc">{{ trainingStats.dias_extra }} entrenos extra fuera de plan</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Racha de Entrenamiento</div>
        <div class="kpi-value">{{ trainingStats.racha_actual }} días</div>
        <div class="kpi-desc">Mejor racha histórica: {{ trainingStats.mejor_racha }} días</div>
      </div>
    </div>

    <!-- Charts Layout Grid -->
    <div class="charts-layout">
      <!-- Weight Card -->
      <div class="chart-card span-full">
        <div class="card-header">
          <div>
            <h3 class="card-title">Evolución del Peso Corporal</h3>
            <p class="card-subtitle">Seguimiento de tu peso corporal en kg</p>
          </div>
          <button class="btn btn-primary" @click="showWeightModal = true">
            Registrar peso hoy
          </button>
        </div>

        <div class="chart-content">
          <!-- Empty State -->
          <div v-if="weightLogs.length < 2" class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <h4 class="empty-title">Faltan registros de peso</h4>
            <p class="empty-desc">Necesitas al menos 2 registros de peso para visualizar el gráfico de evolución.</p>
            <button class="btn btn-primary mt-4" @click="showWeightModal = true">
              Registra tu primer peso
            </button>
          </div>

          <!-- Chart -->
          <div v-else class="chart-wrapper">
            <WeightChart :logs="weightLogs" />
          </div>
        </div>
      </div>

      <!-- Calories Bar Chart -->
      <div class="chart-card">
        <h3 class="card-title">Calorías reales vs objetivo</h3>
        <p class="card-subtitle">Balance de consumo energético diario</p>
        <div class="chart-content chart-wrapper mt-4">
          <canvas id="caloriesChartCanvas"></canvas>
        </div>
      </div>

      <!-- Macros comparison (real vs target) -->
      <div class="chart-card">
        <h3 class="card-title">Consumo de Macronutrientes</h3>
        <p class="card-subtitle">Consumo medio frente a objetivo, por macronutriente</p>
        <div class="chart-content chart-wrapper mt-4">
          <canvas id="macrosChartCanvas"></canvas>
        </div>
      </div>

      <!-- Heatmap Area -->
      <div class="heatmap-wrapper-card span-full">
        <AdherenceHeatmap :logs="heatmapAdherence" :macros="heatmapMacros" />
      </div>
    </div>

    <!-- Weight Register Modal -->
    <div v-if="showWeightModal" class="modal-overlay" @click.self="showWeightModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3 class="modal-title">Registrar Peso</h3>
          <button class="modal-close" @click="showWeightModal = false">&times;</button>
        </div>

        <form @submit.prevent="handleAddWeight" class="modal-form">
          <div class="form-group">
            <label for="modal-peso">Peso (kg)</label>
            <input
              id="modal-peso"
              v-model="newWeight"
              type="number"
              step="0.1"
              placeholder="Ej. 75.5"
              required
              class="form-control"
            />
          </div>

          <div class="form-group">
            <label for="modal-fecha">Fecha</label>
            <input
              id="modal-fecha"
              v-model="newWeightDate"
              type="date"
              required
              class="form-control"
            />
          </div>

          <div class="form-group">
            <label for="modal-nota">Nota opcional</label>
            <input
              id="modal-nota"
              v-model="newWeightNote"
              type="text"
              placeholder="En ayunas, después de entrenar..."
              maxlength="100"
              class="form-control"
            />
          </div>

          <div v-if="formError" class="form-error-msg">
            {{ formError }}
          </div>

          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" @click="showWeightModal = false">
              Cancelar
            </button>
            <button type="submit" class="btn btn-primary" :disabled="savingWeight">
              {{ savingWeight ? 'Guardando...' : 'Guardar Registro' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stats-view {
  padding: 32px;
  background-color: var(--color-bg);
  min-height: 100vh;
  color: var(--color-text);
  font-family: 'Plus Jakarta Sans', sans-serif;
}

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  flex-wrap: wrap;
  gap: 16px;
}

.view-title {
  font-size: 2.25rem;
  font-weight: 700;
  margin: 0 0 4px;
}

.view-subtitle {
  color: var(--color-text-muted);
  margin: 0;
}

/* Range Selector style */
.range-selector {
  display: flex;
  background-color: var(--color-surface);
  border: 1px solid rgba(138, 129, 120, 0.2);
  border-radius: 8px;
  padding: 4px;
}

.range-btn {
  background: none;
  border: none;
  padding: 6px 16px;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: all 0.2s ease;
}

.range-btn.active {
  background-color: var(--color-accent);
  color: var(--color-text);
}

/* KPI Dashboard Cards */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.kpi-card {
  background-color: var(--color-surface);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.kpi-label {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  font-weight: 500;
  margin-bottom: 8px;
}

.kpi-value {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 4px;
}

.kpi-desc {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.text-accent {
  color: var(--color-accent, #A8E063);
}

/* Charts Grid Layout */
.charts-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.span-full {
  grid-column: span 2;
}

.chart-card {
  background-color: var(--color-surface);
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
}

.card-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
}

.card-subtitle {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  margin: 0;
}

.chart-content {
  position: relative;
  min-height: 300px;
}

.chart-wrapper {
  height: 300px;
  width: 100%;
}

/* Empty State visual */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 300px;
  text-align: center;
  padding: 20px;
}

.empty-icon {
  width: 48px;
  height: 48px;
  color: var(--color-text-muted);
  margin-bottom: 12px;
}

.empty-title {
  font-size: 1.125rem;
  font-weight: 600;
  margin: 0 0 4px;
}

.empty-desc {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  max-width: 300px;
  margin: 0;
}

/* Modals */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.modal-content {
  background-color: var(--color-surface);
  width: 90%;
  max-width: 480px;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid rgba(138, 129, 120, 0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: var(--color-text-muted);
}

.modal-form {
  padding: 24px;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 6px;
}

.form-control {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid rgba(138, 129, 120, 0.3);
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.95rem;
  background-color: #FFFFFF;
  color: var(--color-text);
  box-sizing: border-box;
}

.form-control:focus {
  border-color: var(--color-accent);
  outline: none;
}

.form-error-msg {
  color: #DC2626;
  font-size: 0.875rem;
  margin-bottom: 16px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}

/* General Buttons */
.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn-primary {
  background-color: var(--color-accent);
  color: var(--color-text);
}
.btn-primary:hover:not(:disabled) {
  background-color: var(--color-accent-dark);
}

.btn-secondary {
  background-color: rgba(138, 129, 120, 0.1);
  color: var(--color-text);
}
.btn-secondary:hover {
  background-color: rgba(138, 129, 120, 0.2);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.mt-4 {
  margin-top: 16px;
}

/* Responsive constraints */
@media (max-width: 1024px) {
  .charts-layout {
    grid-template-columns: 1fr;
  }
  .span-full {
    grid-column: span 1;
  }
  .stats-view {
    padding: 16px;
    padding-bottom: 90px; /* Space for bottom bar */
  }
}
</style>
