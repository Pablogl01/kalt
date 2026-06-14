<script setup>
import { computed } from 'vue'

const props = defineProps({
  macros: {
    type: Object,
    required: true,
    // Expected structure: { proteina, carbos, grasa, calorias }
  }
})

// Calculate percentage of each macro in terms of grams (or calories, but grams is standard)
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
  <div class="macro-bar-container">
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
