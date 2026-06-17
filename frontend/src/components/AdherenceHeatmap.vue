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

const CELL_SIZE = 24
const CELL_GAP = 6
const COLS_COUNT = 7 // Mon to Sun

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
    const row = Math.floor(diffDays / 7)
    const col = diffDays % 7

    const macro = macrosMap.value[log.fecha] || {}

    return {
      ...log,
      row,
      col,
      isToday: log.fecha === todayStr,
      caloriasReales: macro.calorias_reales || 0,
      caloriasObjetivo: macro.calorias_objetivo || 0,
    }
  })

  // Keep last 52 weeks max
  const maxRow = Math.max(...mapped.map(m => m.row), 0)
  const minRowToShow = Math.max(maxRow - 51, 0)

  return mapped
    .filter(m => m.row >= minRowToShow)
    .map(m => ({
      ...m,
      row: m.row - minRowToShow
    }))
})

const totalRows = computed(() => {
  if (gridData.value.length === 0) return 0
  return Math.max(...gridData.value.map(m => m.row), 0) + 1
})

const getCellColor = (level) => {
  if (level === 'completa') return '#15803D' // --color-success-adherence
  if (level === 'parcial') return '#86EFAC'  // --color-partial-adherence
  return '#374151' // sin_datos / future
}

const handleMouseEnter = (event, cell) => {
  hoveredCell.value = cell
  const rect = event.currentTarget.getBoundingClientRect()
  const parentRect = event.currentTarget.closest('.heatmap-container').getBoundingClientRect()
  
  // Position tooltip relative to container
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
</script>

<template>
  <div class="heatmap-card">
    <h3 class="heatmap-title">Calendario de Adherencia</h3>
    <p class="heatmap-subtitle">Visualización de tu dieta y entrenamiento en el último año</p>

    <div class="heatmap-container">
      <div class="heatmap-scrollable">
        <svg
          :width="COLS_COUNT * (CELL_SIZE + CELL_GAP) + 30"
          :height="totalRows * (CELL_SIZE + CELL_GAP) + 30"
          class="heatmap-svg"
        >
          <!-- Headers L M X J V S D -->
          <g class="headers">
            <text
              v-for="(day, index) in ['L', 'M', 'X', 'J', 'V', 'S', 'D']"
              :key="index"
              :x="index * (CELL_SIZE + CELL_GAP) + CELL_SIZE / 2 + 20"
              y="15"
              text-anchor="middle"
              class="header-text"
            >
              {{ day }}
            </text>
          </g>

          <!-- Heatmap Cells -->
          <g transform="translate(20, 25)">
            <rect
              v-for="cell in gridData"
              :key="cell.fecha"
              :x="cell.col * (CELL_SIZE + CELL_GAP)"
              :y="cell.row * (CELL_SIZE + CELL_GAP)"
              :width="CELL_SIZE"
              :height="CELL_SIZE"
              rx="4"
              :fill="getCellColor(cell.nivel)"
              :stroke="cell.isToday ? '#A8E063' : 'none'"
              :stroke-width="cell.isToday ? 2 : 0"
              class="heatmap-cell"
              @mouseenter="handleMouseEnter($event, cell)"
              @mouseleave="handleMouseLeave"
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
            <span :class="'adherence-' + hoveredCell.nivel">
              {{ hoveredCell.nivel === 'completa' ? 'Completa' : hoveredCell.nivel === 'parcial' ? 'Parcial' : 'Sin datos' }}
            </span>
          </div>
          <div class="tooltip-calories">
            Kcal: {{ Math.round(hoveredCell.caloriasReales) }} / {{ Math.round(hoveredCell.caloriasObjetivo) || '-' }}
          </div>
          <div class="tooltip-training">
            Entrenó: 
            <span v-if="hoveredCell.entreno_planificado === null">Sin registrar</span>
            <span v-else-if="hoveredCell.entreno_planificado && hoveredCell.ha_entrenado">Sí (Planificado)</span>
            <span v-else-if="hoveredCell.entreno_planificado && !hoveredCell.ha_entrenado">No (Planificado)</span>
            <span v-else-if="!hoveredCell.entreno_planificado && hoveredCell.ha_entrenado">Sí (Extra)</span>
            <span v-else>No planificado</span>
          </div>
          <div class="tooltip-arrow"></div>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="heatmap-legend">
      <span class="legend-text">Menos adherencia</span>
      <div class="legend-scale">
        <span class="scale-box" style="background-color: #374151"></span>
        <span class="scale-box" style="background-color: #86EFAC"></span>
        <span class="scale-box" style="background-color: #15803D"></span>
      </div>
      <span class="legend-text">Más adherencia</span>
    </div>
  </div>
</template>

<style scoped>
.heatmap-card {
  background-color: var(--color-surface-dark);
  color: #FFFFFF;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
}

.heatmap-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0 0 4px;
  color: #FFFFFF;
}

.heatmap-subtitle {
  font-size: 0.875rem;
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
  overflow-y: auto;
  max-height: 380px;
  padding: 8px 0;
  scrollbar-width: thin;
  scrollbar-color: #4B5563 transparent;
}

.heatmap-scrollable::-webkit-scrollbar {
  width: 6px;
}
.heatmap-scrollable::-webkit-scrollbar-thumb {
  background-color: #4B5563;
  border-radius: 3px;
}

.heatmap-svg {
  user-select: none;
}

.header-text {
  font-size: 0.75rem;
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
  border-radius: 8px;
  font-size: 0.75rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
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
  font-size: 0.75rem;
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
  border-radius: 2px;
  display: inline-block;
}
</style>
