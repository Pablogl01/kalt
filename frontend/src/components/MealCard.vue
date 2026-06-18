<script setup>
import { computed } from 'vue'
import { RefreshCw, CircleCheck } from 'lucide-vue-next'
import { useUserStore } from '@/stores/userStore'

const props = defineProps({
  mealLog: {
    type: Object,
    required: true
  }
})

const userStore = useUserStore()

const emit = defineEmits(['complete', 'skip', 'substitute'])

const isAllergic = (foodId) => {
  if (!userStore.user?.food_restrictions) return false
  return userStore.user.food_restrictions.some(
    r => r.food_id === foodId && r.tipo === 'alergia'
  )
}

// Computed properties to calculate actual macros of this meal log from its items
const actualMacros = computed(() => {
  let calorias = 0
  let proteina = 0
  let carbos = 0
  let grasa = 0

  if (props.mealLog.meal_log_items) {
    props.mealLog.meal_log_items.forEach(item => {
      calorias += parseFloat(item.calorias || 0)
      proteina += parseFloat(item.proteina || 0)
      carbos += parseFloat(item.carbos || 0)
      grasa += parseFloat(item.grasa || 0)
    })
  }

  return {
    calorias: Math.round(calorias),
    proteina: Math.round(proteina),
    carbos: Math.round(carbos),
    grasa: Math.round(grasa)
  }
})

// For planned meals, we can sum the target macros if available in props.mealLog.meal.meal_items
const plannedMacros = computed(() => {
  if (!props.mealLog.meal?.meal_items) {
    return null
  }

  let calorias = 0
  let proteina = 0
  let carbos = 0
  let grasa = 0

  props.mealLog.meal.meal_items.forEach(item => {
    calorias += parseFloat(item.calorias || 0)
    proteina += parseFloat(item.proteina || 0)
    carbos += parseFloat(item.carbos || 0)
    grasa += parseFloat(item.grasa || 0)
  })

  return {
    calorias: Math.round(calorias),
    proteina: Math.round(proteina),
    carbos: Math.round(carbos),
    grasa: Math.round(grasa)
  }
})

const formatTime = (timeStr) => {
  if (!timeStr) return ''
  const parts = timeStr.split(':')
  return `${parts[0]}:${parts[1]}`
}
</script>

<template>
  <div 
    class="meal-card" 
    :class="{ 
      'meal-card--completed': mealLog.realizada, 
      'meal-card--extra': mealLog.es_extra 
    }"
  >
    <div class="meal-header">
      <div class="meal-title-wrap">
        <span v-if="mealLog.es_extra" class="badge-extra">Extra</span>
        <h3 class="meal-name">
          {{ mealLog.meal?.nombre || 'Comida Extra' }}
        </h3>
        <span v-if="mealLog.meal?.meal_slot?.es_pre_entreno" class="badge-pre">Pre-entreno</span>
        <span v-if="mealLog.meal?.meal_slot?.es_post_entreno" class="badge-post">Post-entreno</span>
      </div>

      <div class="meal-time">
        <span v-if="mealLog.realizada && mealLog.hora_real" class="time-real">
          Realizada a las {{ formatTime(mealLog.hora_real) }}
        </span>
        <span v-else-if="mealLog.meal?.hora_objetivo" class="time-planned">
          Planificado: {{ formatTime(mealLog.meal.hora_objetivo) }}
        </span>
      </div>
    </div>

    <!-- Foods List -->
    <div class="meal-body">
      <!-- If completed or extra: show logged items -->
      <ul v-if="mealLog.realizada || mealLog.es_extra" class="food-list">
        <li v-for="item in mealLog.meal_log_items" :key="item.id" class="food-item">
          <span class="food-name">{{ item.food?.nombre }}</span>
          <span class="food-weight">{{ Math.round(item.cantidad_gramos) }}g</span>
        </li>
        <p v-if="!mealLog.meal_log_items || mealLog.meal_log_items.length === 0" class="empty-food">
          Sin alimentos registrados
        </p>
      </ul>

      <!-- If not completed and has planned meal items: show planned items -->
      <ul v-else-if="mealLog.meal?.meal_items" class="food-list food-list--planned">
        <li v-for="item in mealLog.meal.meal_items" :key="item.id" class="food-item">
          <span class="food-name">
            {{ item.food?.nombre }}
            <span class="food-weight">({{ Math.round(item.cantidad_gramos) }}g)</span>
          </span>
          <button 
            v-if="!isAllergic(item.food_id)" 
            @click="emit('substitute', item)" 
            class="btn-substitute"
          >
            <RefreshCw :size="14" :stroke-width="2" aria-hidden="true" />
            Sustituir
          </button>
        </li>
      </ul>
    </div>

    <!-- Macros summary & Actions -->
    <div class="meal-footer">
      <div class="macros-summary">
        <div class="macro-mini">
          <span class="macro-dot dot-protein"></span>
          <span>{{ mealLog.realizada ? actualMacros.proteina : (plannedMacros?.proteina || 0) }}g</span>
        </div>
        <div class="macro-mini">
          <span class="macro-dot dot-carbs"></span>
          <span>{{ mealLog.realizada ? actualMacros.carbos : (plannedMacros?.carbos || 0) }}g</span>
        </div>
        <div class="macro-mini">
          <span class="macro-dot dot-fat"></span>
          <span>{{ mealLog.realizada ? actualMacros.grasa : (plannedMacros?.grasa || 0) }}g</span>
        </div>
        <div class="macro-mini calories-mini">
          <span>{{ mealLog.realizada ? actualMacros.calorias : (plannedMacros?.calorias || 0) }} kcal</span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="meal-actions" v-if="!mealLog.es_extra">
        <button 
          v-if="!mealLog.realizada" 
          @click="emit('complete', mealLog.id)" 
          class="btn-action btn-action--complete"
        >
          Marcar como realizada
        </button>
        <div v-else class="completed-actions-wrap">
          <span class="completed-indicator"><CircleCheck :size="14" :stroke-width="2" aria-hidden="true" /> Realizada</span>
          <button 
            @click="emit('skip', mealLog.id)" 
            class="btn-action btn-action--skip"
          >
            Saltar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.meal-card {
  background: var(--color-surface);
  border: 1px solid rgba(138, 129, 120, 0.15);
  border-radius: 16px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  transition: all 0.25s ease;
}

.meal-card--completed {
  border-color: rgba(22, 163, 74, 0.3);
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.04);
}

.meal-card--extra {
  border-color: rgba(37, 99, 235, 0.2);
}

.meal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.meal-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.meal-name {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
}

.badge-extra, .badge-pre, .badge-post {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.badge-extra {
  background-color: rgba(37, 99, 235, 0.1);
  color: var(--color-calories);
}

.badge-pre {
  background-color: rgba(234, 88, 12, 0.1);
  color: var(--color-carbs);
}

.badge-post {
  background-color: rgba(22, 163, 74, 0.1);
  color: var(--color-protein);
}

.meal-time {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.time-real {
  color: var(--color-protein);
  font-weight: 500;
}

.meal-body {
  border-top: 1px solid rgba(138, 129, 120, 0.1);
  border-bottom: 1px solid rgba(138, 129, 120, 0.1);
  padding: 0.75rem 0;
}

.food-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.food-list--planned {
  opacity: 0.75;
}

.food-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.875rem;
}

.food-name {
  color: var(--color-text);
}

.food-weight {
  color: var(--color-text-muted);
  font-weight: 500;
}

.empty-food {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  font-style: italic;
  margin: 0;
}

.meal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.macros-summary {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.8125rem;
  font-weight: 600;
}

.macro-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 0.25rem;
}

.dot-protein { background-color: var(--color-protein); }
.dot-carbs { background-color: var(--color-carbs); }
.dot-fat { background-color: var(--color-fat); }

.macro-mini {
  display: flex;
  align-items: center;
  color: var(--color-text);
}

.calories-mini {
  color: var(--color-calories);
}

.completed-actions-wrap {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.completed-indicator {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-protein);
}

.btn-action {
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 0.375rem 0.875rem;
  border-radius: 8px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s;
}

.btn-action--complete {
  background-color: var(--color-accent);
  color: var(--color-text);
}

.btn-action--complete:hover {
  background-color: var(--color-accent-dark);
}

.btn-action--skip {
  background-color: transparent;
  border-color: rgba(138, 129, 120, 0.3);
  color: var(--color-text-muted);
}

.btn-action--skip:hover {
  background-color: rgba(138, 129, 120, 0.05);
  border-color: rgba(138, 129, 120, 0.5);
  color: var(--color-text);
}

.btn-substitute {
  background: none;
  border: none;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-system);
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.btn-substitute:hover {
  background-color: rgba(37, 99, 235, 0.08);
}
</style>
