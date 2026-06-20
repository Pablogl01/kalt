<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  logs: {
    type: Array,
    required: true
  },
  macros: {
    type: Array,
    default: () => []
  }
})

const macrosMap = computed(() => {
  const map = {}
  props.macros.forEach(m => {
    map[m.fecha] = m
  })
  return map
})

const hoveredCell = ref(null)
const tooltipX = ref(0)
const tooltipY = ref(0)

const CELL_SIZE = 14
const CELL_GAP = 4
const ROWS_COUNT = 7 // Mon to Sun

const gridData = computed(() => {
  if (!props.logs || props.logs.length === 0) return []

  const sortedLogs = [...props.logs].sort((a, b) => a.fecha.localeCompare(b.fecha))
  const firstLogDateStr = sortedLogs[0].fecha
  const parts = firstLogDateStr.split('-')
  const firstDate = new Date(parts[0], parts[1] - 1, parts[2])
  
  // Align startMonday
  const firstDayOfWeek = (firstDate.getDay() + 6) % 7 // Monday = 0
  const startMonday = new Date(firstDate)
  startMonday.setDate(firstDate.getDate() - firstDayOfWeek)

  const todayStr = new Date().toLocaleDateString('en-CA') // YYYY-MM-DD local format

  const mapped = sortedLogs.map(log => {
    const logDateParts = log.fecha.split('-')
    const logDate = new Date(logDateParts[0], logDateParts[1] - 1, logDateParts[2])
    const diffDays = Math.round((logDate.getTime() - startMonday.getTime()) / (1000 * 60 * 60 * 24))
    
    // Switch row and col for horizontal layout
    const col = Math.floor(diffDays / 7)
    const row = diffDays % 7

    const macro = macrosMap.value[log.fecha] || {}

    return {
      ...log,
      row,
      col,
      logDate,
      isToday: log.fecha === todayStr,
      caloriasReales: macro.calorias_reales || 0,
      caloriasObjetivo: macro.calorias_objetivo || 0,
    }
  })

  // Keep last 52 weeks max
  const maxCol = Math.max(...mapped.map(m => m.col), 0)
  const minColToShow = Math.max(maxCol - 51, 0)

  return mapped
    .filter(m => m.col >= minColToShow)
    .map(m => ({
      ...m,
      col: m.col - minColToShow
    }))
})

const totalCols = computed(() => {
  if (gridData.value.length === 0) return 0
  return Math.max(...gridData.value.map(m => m.col), 0) + 1
})

const monthLabels = computed(() => {
  if (gridData.value.length === 0) return []
  
  const labels = []
  let lastMonth = -1
  
  // We sort by col and row to scan chronologically
  const sorted = [...gridData.value].sort((a, b) => a.col !== b.col ? a.col - b.col : a.row - b.row)
  
  for (const cell of sorted) {
    // Only put a label if it's the first week of a month (roughly)
    const month = cell.logDate.getMonth()
    if (month !== lastMonth) {
      // Avoid putting two labels too close to each other
      if (labels.length === 0 || (cell.col - labels[labels.length - 1].col) > 2) {
        const monthName = cell.logDate.toLocaleDateString('es-ES', { month: 'short' })
        labels.push({
          col: cell.col,
          label: monthName.charAt(0).toUpperCase() + monthName.slice(1)
        })
      }
      lastMonth = month
    }
  }
  return labels
})

const getCellColor = (level) => {
  if (level === 'completa') return '#15803D' // --color-success-adherence
  if (level === 'parcial') return '#86EFAC'  // --color-partial-adherence
  return '#E8DDD4' // sin_datos / future (light empty cell)
}

const handleMouseEnter = (event, cell) => {
  hoveredCell.value = cell
  const rect = event.currentTarget.getBoundingClientRect()
  const parentRect = event.currentTarget.closest('.heatmap-container').getBoundingClientRect()
  
  tooltipX.value = rect.left - parentRect.left + rect.width / 2
  tooltipY.value = rect.top - parentRect.top - 110
}

const handleMouseLeave = () => {
  hoveredCell.value = null
}

const formatDateLabel = (dateStr) => {
  const parts = dateStr.split('-')
  const date = new Date(parts[0], parts[1] - 1, parts[2])
  const formatted = date.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'short' })
  return formatted.charAt(0).toUpperCase() + formatted.slice(1)
}

const adherenceText = (nivel) =>
  nivel === 'completa' ? 'Completa' : nivel === 'parcial' ? 'Parcial' : 'Sin datos'

const trainingText = (cell) => {
  if (cell.entreno_planificado === null || cell.entreno_planificado === undefined) return 'Sin registrar'
  if (cell.entreno_planificado && cell.ha_entrenado) return 'Sí (planificado)'
  if (cell.entreno_planificado && !cell.ha_entrenado) return 'No (planificado)'
  if (!cell.entreno_planificado && cell.ha_entrenado) return 'Sí (extra)'
  return 'No planificado'
}

// Full screen-reader description for a single day cell.
const cellLabel = (cell) => {
  const kcal = `${Math.round(cell.caloriasReales)} de ${Math.round(cell.caloriasObjetivo) || '—'} kcal`
  return `${formatDateLabel(cell.fecha)}. Adherencia: ${adherenceText(cell.nivel)}. ${kcal}. Entreno: ${trainingText(cell)}.`
}

// Equivalent textual alternative to the colour grid.
const periodSummary = computed(() => {
  const counts = { completa: 0, parcial: 0, otros: 0 }
  gridData.value.forEach(c => {
    if (c.nivel === 'completa') counts.completa++
    else if (c.nivel === 'parcial') counts.parcial++
    else counts.otros++
  })
  return `Calendario de adherencia: ${gridData.value.length} días. `
    + `${counts.completa} con adherencia completa, ${counts.parcial} parcial y ${counts.otros} sin datos. `
    + `Usa el tabulador para explorar el detalle de cada día.`
})
</script>

<template>
  <div class="heatmap-card">
    <h3 class="heatmap-title">Calendario de Adherencia</h3>
    <p class="heatmap-subtitle">Visualización de tu dieta y entrenamiento en el último año</p>

    <!-- Textual alternative for screen readers -->
    <p class="sr-only">{{ periodSummary }}</p>

    <div class="heatmap-container">
      <div class="heatmap-scrollable">
        <svg
          :width="totalCols * (CELL_SIZE + CELL_GAP) + 40"
          :height="ROWS_COUNT * (CELL_SIZE + CELL_GAP) + 30"
          class="heatmap-svg"
          role="group"
          aria-label="Calendario de adherencia por día"
        >
          <!-- Month Headers -->
          <g class="month-headers">
            <text
              v-for="(month, index) in monthLabels"
              :key="'m-'+index"
              :x="month.col * (CELL_SIZE + CELL_GAP) + 30"
              y="12"
              class="header-text"
            >
              {{ month.label }}
            </text>
          </g>

          <!-- Day Headers L M X J V S D -->
          <g class="day-headers">
            <text
              v-for="(day, index) in ['L', 'M', 'X', 'J', 'V', 'S', 'D']"
              :key="index"
              x="15"
              :y="index * (CELL_SIZE + CELL_GAP) + 25 + CELL_SIZE / 2"
              text-anchor="middle"
              alignment-baseline="central"
              class="header-text"
              :style="{ display: index % 2 === 0 ? 'block' : 'none' }" 
            >
              {{ day }}
            </text>
          </g>

          <!-- Heatmap Cells -->
          <g transform="translate(30, 25)">
            <rect
              v-for="cell in gridData"
              :key="cell.fecha"
              :x="cell.col * (CELL_SIZE + CELL_GAP)"
              :y="cell.row * (CELL_SIZE + CELL_GAP)"
              :width="CELL_SIZE"
              :height="CELL_SIZE"
              rx="2"
              :fill="getCellColor(cell.nivel)"
              :stroke="cell.isToday ? '#FFD400' : 'none'"
              :stroke-width="cell.isToday ? 2 : 0"
              class="heatmap-cell"
              tabindex="0"
              role="img"
              :aria-label="cellLabel(cell)"
              @mouseenter="handleMouseEnter($event, cell)"
              @mouseleave="handleMouseLeave"
              @focus="handleMouseEnter($event, cell)"
              @blur="handleMouseLeave"
            />
          </g>
        </svg>

        <!-- Tooltip -->
        <div
          v-if="hoveredCell"
          class="heatmap-tooltip"
          :style="{ left: tooltipX + 'px', top: tooltipY + 'px' }"
        >
          <div class="tooltip-date">{{ formatDateLabel(hoveredCell.fecha) }}</div>
          <div class="tooltip-adherence">
            Adherencia:
            <span :class="'adherence-' + hoveredCell.nivel">{{ adherenceText(hoveredCell.nivel) }}</span>
          </div>
          <div class="tooltip-calories">
            Kcal: {{ Math.round(hoveredCell.caloriasReales) }} / {{ Math.round(hoveredCell.caloriasObjetivo) || '-' }}
          </div>
          <div class="tooltip-training">
            Entrenó: <span>{{ trainingText(hoveredCell) }}</span>
          </div>
          <div class="tooltip-arrow"></div>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="heatmap-legend">
      <span class="legend-text">Menos adherencia</span>
      <div class="legend-scale">
        <span class="scale-box" style="background-color: #E8DDD4" role="img" aria-label="Sin datos"></span>
        <span class="scale-box" style="background-color: #86EFAC" role="img" aria-label="Adherencia parcial"></span>
        <span class="scale-box" style="background-color: #15803D" role="img" aria-label="Adherencia completa"></span>
      </div>
      <span class="legend-text">Más adherencia</span>
    </div>
  </div>
</template>

<style scoped>
.heatmap-card {
  background-color: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: var(--shadow-md);
  display: flex;
  flex-direction: column;
}

.heatmap-title {
  font-size: var(--fs-lg);
  font-weight: 600;
  margin: 0 0 4px;
  color: var(--color-text);
}

.heatmap-subtitle {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
  margin: 0 0 20px;
}

.heatmap-container {
  position: relative;
  width: 100%;
}

.heatmap-scrollable {
  display: flex;
  justify-content: center;
  overflow: hidden; /* No scroll as requested */
  padding: 8px 0;
  width: 100%;
}

.heatmap-svg {
  user-select: none;
  max-width: 100%;
}

.header-text {
  font-size: var(--fs-xs);
  font-weight: 600;
  fill: var(--color-text-muted);
}

.heatmap-cell {
  cursor: pointer;
  transition: opacity 0.15s ease;
}
.heatmap-cell:hover {
  opacity: 0.8;
}

.heatmap-tooltip {
  position: absolute;
  background-color: #000000;
  color: #FFFFFF;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  font-size: var(--fs-xs);
  box-shadow: var(--shadow-lg);
  pointer-events: none;
  z-index: 10;
  transform: translateX(-50%);
  border: 1px solid #374151;
  min-width: 140px;
}

.tooltip-date {
  font-weight: 600;
  margin-bottom: 4px;
  border-bottom: 1px solid #2D3748;
  padding-bottom: 4px;
}

.tooltip-adherence span {
  font-weight: 600;
}
.adherence-completa {
  color: #4ADE80;
}
.adherence-parcial {
  color: #FBBF24;
}
.adherence-sin_datos {
  color: #9CA3AF;
}

.tooltip-calories, .tooltip-training {
  margin-top: 2px;
}

.tooltip-arrow {
  position: absolute;
  bottom: -6px;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 6px solid transparent;
  border-right: 6px solid transparent;
  border-top: 6px solid #000000;
}

.heatmap-legend {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  margin-top: 16px;
  font-size: var(--fs-xs);
}

.legend-text {
  color: var(--color-text-muted);
}

.legend-scale {
  display: flex;
  gap: 4px;
}

.scale-box {
  width: 12px;
  height: 12px;
  border-radius: var(--radius-sm);
  display: inline-block;
}
</style>
