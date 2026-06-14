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

// Calculations for consumption progress percentages
const progress = computed(() => {
  if (!props.consumed) return null

  const getPct = (c, t) => {
    const target = parseFloat(t || 0)
    if (target <= 0) return 0
    return Math.min(100, Math.round((parseFloat(c || 0) / target) * 100))
  }

  return {
    calorias: getPct(props.consumed.calorias, props.macros.calorias),
    proteina: getPct(props.consumed.proteina, props.macros.proteina),
    carbos: getPct(props.consumed.carbos, props.macros.carbos),
    grasa: getPct(props.consumed.grasa, props.macros.grasa),
  }
})

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
        </span>
      </div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill bar-calories" :style="{ width: progress.calorias + '%' }"></div>
      </div>
    </div>

    <!-- Protein -->
    <div class="progress-card">
      <div class="progress-info">
        <span class="progress-label">Proteína</span>
        <span class="progress-nums text-protein">
          <strong>{{ Math.round(consumed.proteina) }}g</strong> / {{ Math.round(macros.proteina) }}g
        </span>
      </div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill bar-protein" :style="{ width: progress.proteina + '%' }"></div>
      </div>
    </div>

    <!-- Carbs -->
    <div class="progress-card">
      <div class="progress-info">
        <span class="progress-label">Carbohidratos</span>
        <span class="progress-nums text-carbs">
          <strong>{{ Math.round(consumed.carbos) }}g</strong> / {{ Math.round(macros.carbos) }}g
        </span>
      </div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill bar-carbs" :style="{ width: progress.carbos + '%' }"></div>
      </div>
    </div>

    <!-- Fats -->
    <div class="progress-card">
      <div class="progress-info">
        <span class="progress-label">Grasas</span>
        <span class="progress-nums text-fat">
          <strong>{{ Math.round(consumed.grasa) }}g</strong> / {{ Math.round(macros.grasa) }}g
        </span>
      </div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill bar-fat" :style="{ width: progress.grasa + '%' }"></div>
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
