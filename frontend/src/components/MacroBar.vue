<script setup>
import { computed } from 'vue'

const props = defineProps({
  // Target macros (e.g., from the day plan or profile)
  macros: {
    type: Object,
    required: true,
  },
  // Optional consumed macros (e.g., from checked meal logs)
  consumed: {
    type: Object,
    default: null,
  }
})

// Calculations for consumption progress.
// The bar fill is capped at 100% (it can't overflow its track), but we also
// expose whether the target was exceeded and by how much, so overconsumption
// is communicated instead of silently clamped.
const progress = computed(() => {
  if (!props.consumed) return null

  const build = (c, t) => {
    const consumed = parseFloat(c || 0)
    const target = parseFloat(t || 0)
    if (target <= 0) return { pct: 0, over: false, excess: 0 }
    const rawPct = Math.round((consumed / target) * 100)
    const over = consumed > target
    return {
      pct: Math.min(100, rawPct),
      over,
      excess: over ? Math.round(consumed - target) : 0,
    }
  }

  return {
    calorias: build(props.consumed.calorias, props.macros.calorias),
    proteina: build(props.consumed.proteina, props.macros.proteina),
    carbos: build(props.consumed.carbos, props.macros.carbos),
    grasa: build(props.consumed.grasa, props.macros.grasa),
  }
})

// Accessible label for each progress bar, including overconsumption.
const MACRO_NAMES = { calorias: 'Calorías', proteina: 'Proteína', carbos: 'Carbohidratos', grasa: 'Grasas' }
const macroUnit = (key) => (key === 'calorias' ? 'kcal' : 'g')

function ariaLabelFor(key) {
  const consumed = Math.round(parseFloat(props.consumed?.[key] || 0))
  const target = Math.round(parseFloat(props.macros?.[key] || 0))
  const p = progress.value?.[key]
  let label = `${MACRO_NAMES[key]}: ${consumed} de ${target} ${macroUnit(key)}`
  if (p?.over) label += `, objetivo superado en ${p.excess} ${macroUnit(key)}`
  return label
}

// Original proportions logic (used if not showing consumption progress)
const totalGrams = computed(() => {
  const p = parseFloat(props.macros?.proteina || 0)
  const c = parseFloat(props.macros?.carbos || 0)
  const f = parseFloat(props.macros?.grasa || 0)
  return p + c + f || 1
})

const proteinPct = computed(() => (parseFloat(props.macros?.proteina || 0) / totalGrams.value) * 100)
const carbsPct = computed(() => (parseFloat(props.macros?.carbos || 0) / totalGrams.value) * 100)
const fatPct = computed(() => (parseFloat(props.macros?.grasa || 0) / totalGrams.value) * 100)
</script>

<template>
  <div v-if="consumed" class="macro-progress-grid">
    <!-- Calories -->
    <div class="progress-card">
      <div class="progress-info">
        <span class="progress-label">Calorías</span>
        <span class="progress-nums text-calories">
          <strong>{{ Math.round(consumed.calorias) }}</strong> / {{ Math.round(macros.calorias) }} kcal
          <span v-if="progress.calorias.over" class="progress-excess">+{{ progress.calorias.excess }} kcal</span>
        </span>
      </div>
      <div
        class="progress-bar-bg"
        role="progressbar"
        aria-valuemin="0"
        :aria-valuenow="Math.round(consumed.calorias)"
        :aria-valuemax="Math.round(macros.calorias)"
        :aria-label="ariaLabelFor('calorias')"
      >
        <div
          class="progress-bar-fill bar-calories"
          :class="{ 'progress-bar-fill--over': progress.calorias.over }"
          :style="{ width: progress.calorias.pct + '%' }"
        ></div>
      </div>
    </div>

    <!-- Protein -->
    <div class="progress-card">
      <div class="progress-info">
        <span class="progress-label">Proteína</span>
        <span class="progress-nums text-protein">
          <strong>{{ Math.round(consumed.proteina) }}g</strong> / {{ Math.round(macros.proteina) }}g
          <span v-if="progress.proteina.over" class="progress-excess">+{{ progress.proteina.excess }}g</span>
        </span>
      </div>
      <div
        class="progress-bar-bg"
        role="progressbar"
        aria-valuemin="0"
        :aria-valuenow="Math.round(consumed.proteina)"
        :aria-valuemax="Math.round(macros.proteina)"
        :aria-label="ariaLabelFor('proteina')"
      >
        <div
          class="progress-bar-fill bar-protein"
          :class="{ 'progress-bar-fill--over': progress.proteina.over }"
          :style="{ width: progress.proteina.pct + '%' }"
        ></div>
      </div>
    </div>

    <!-- Carbs -->
    <div class="progress-card">
      <div class="progress-info">
        <span class="progress-label">Carbohidratos</span>
        <span class="progress-nums text-carbs">
          <strong>{{ Math.round(consumed.carbos) }}g</strong> / {{ Math.round(macros.carbos) }}g
          <span v-if="progress.carbos.over" class="progress-excess">+{{ progress.carbos.excess }}g</span>
        </span>
      </div>
      <div
        class="progress-bar-bg"
        role="progressbar"
        aria-valuemin="0"
        :aria-valuenow="Math.round(consumed.carbos)"
        :aria-valuemax="Math.round(macros.carbos)"
        :aria-label="ariaLabelFor('carbos')"
      >
        <div
          class="progress-bar-fill bar-carbs"
          :class="{ 'progress-bar-fill--over': progress.carbos.over }"
          :style="{ width: progress.carbos.pct + '%' }"
        ></div>
      </div>
    </div>

    <!-- Fats -->
    <div class="progress-card">
      <div class="progress-info">
        <span class="progress-label">Grasas</span>
        <span class="progress-nums text-fat">
          <strong>{{ Math.round(consumed.grasa) }}g</strong> / {{ Math.round(macros.grasa) }}g
          <span v-if="progress.grasa.over" class="progress-excess">+{{ progress.grasa.excess }}g</span>
        </span>
      </div>
      <div
        class="progress-bar-bg"
        role="progressbar"
        aria-valuemin="0"
        :aria-valuenow="Math.round(consumed.grasa)"
        :aria-valuemax="Math.round(macros.grasa)"
        :aria-label="ariaLabelFor('grasa')"
      >
        <div
          class="progress-bar-fill bar-fat"
          :class="{ 'progress-bar-fill--over': progress.grasa.over }"
          :style="{ width: progress.grasa.pct + '%' }"
        ></div>
      </div>
    </div>
  </div>

  <div v-else class="macro-bar-container">
    <!-- Visual bar -->
    <div class="bar-visual">
      <div 
        class="bar-segment bar-segment--protein" 
        :style="{ width: proteinPct + '%' }" 
        title="Proteína"
      ></div>
      <div 
        class="bar-segment bar-segment--carbs" 
        :style="{ width: carbsPct + '%' }" 
        title="Carbohidratos"
      ></div>
      <div 
        class="bar-segment bar-segment--fat" 
        :style="{ width: fatPct + '%' }" 
        title="Grasas"
      ></div>
    </div>

    <!-- Numerical indicators -->
    <div class="bar-stats">
      <div class="stat-item">
        <span class="dot dot--protein"></span>
        <span class="stat-label">Prot:</span>
        <span class="stat-value">{{ Math.round(macros?.proteina) }}g</span>
      </div>
      <div class="stat-item">
        <span class="dot dot--carbs"></span>
        <span class="stat-label">Carb:</span>
        <span class="stat-value">{{ Math.round(macros?.carbos) }}g</span>
      </div>
      <div class="stat-item">
        <span class="dot dot--fat"></span>
        <span class="stat-label">Grasa:</span>
        <span class="stat-value">{{ Math.round(macros?.grasa) }}g</span>
      </div>
      <div class="stat-item stat-item--calories">
        <span class="stat-value stat-value--kcal">{{ Math.round(macros?.calorias) }} kcal</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Progress Bars styling */
.macro-progress-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  width: 100%;
}

@media (min-width: 640px) {
  .macro-progress-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.progress-card {
  background: var(--color-surface);
  border: 1px solid rgba(138, 129, 120, 0.15);
  border-radius: 12px;
  padding: 0.875rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.progress-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.progress-nums {
  font-size: 0.8125rem;
  font-weight: 500;
}

.progress-bar-bg {
  height: 8px;
  background-color: rgba(138, 129, 120, 0.1);
  border-radius: 4px;
  width: 100%;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Colors from DESIGN.md */
.bar-calories { background-color: var(--color-calories); }
.bar-protein  { background-color: var(--color-protein); }
.bar-carbs    { background-color: var(--color-carbs); }
.bar-fat      { background-color: var(--color-fat); }

/* Overconsumption: bar stays full, a red cap marks the exceeded target */
.progress-bar-fill--over {
  box-shadow: inset -4px 0 0 var(--color-negative-badge);
}

.progress-excess {
  margin-left: 0.25rem;
  font-weight: 700;
  color: var(--color-negative-badge);
}

.text-calories { color: var(--color-calories); }
.text-protein  { color: var(--color-protein); }
.text-carbs    { color: var(--color-carbs); }
.text-fat      { color: var(--color-fat); }

/* Classic Proportional Bar styling */
.macro-bar-container {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
}

.bar-visual {
  height: 6px;
  border-radius: 3px;
  background: rgba(138, 129, 120, 0.15);
  display: flex;
  overflow: hidden;
}

.bar-segment {
  height: 100%;
  transition: width 0.3s ease;
}

.bar-segment--protein { background-color: var(--color-protein); }
.bar-segment--carbs   { background-color: var(--color-carbs); }
.bar-segment--fat     { background-color: var(--color-fat); }

.bar-stats {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.75rem;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.dot--protein { background-color: var(--color-protein); }
.dot--carbs   { background-color: var(--color-carbs); }
.dot--fat     { background-color: var(--color-fat); }

.stat-label {
  color: var(--color-text-muted);
  font-weight: 500;
}

.stat-value {
  color: var(--color-text);
  font-weight: 600;
}

.stat-item--calories {
  margin-left: auto;
}

.stat-value--kcal {
  color: var(--color-calories);
  font-weight: 700;
}
</style>
