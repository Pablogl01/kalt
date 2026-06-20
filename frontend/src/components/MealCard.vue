<script setup>
import { computed, ref } from 'vue'
import { motion } from 'motion-v'
import { RefreshCw, CircleCheck, CircleSlash } from 'lucide-vue-next'
import { useUserStore } from '@/stores/userStore'
import { spring, tap, tapSubtle } from '@/lib/motion'

const props = defineProps({
  mealLog: {
    type: Object,
    required: true
  }
})

const userStore = useUserStore()

const emit = defineEmits(['complete', 'skip', 'reset', 'substitute', 'regenerate'])

const isRegenerating = ref(false)

function handleRegenerate() {
  if (isRegenerating.value) return
  isRegenerating.value = true
  emit('regenerate', props.mealLog.meal.id)
}

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
  <motion.div
    layout
    :transition="spring.gentle"
    class="meal-card"
    :class="{
      'meal-card--completed': mealLog.realizada,
      'meal-card--skipped': mealLog.saltada,
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
        <span v-if="mealLog.ajuste_kcal" class="badge-ajuste" title="Ajuste automático por un evento del día">
          Ajustado {{ mealLog.ajuste_kcal > 0 ? '+' : '−' }}{{ Math.abs(mealLog.ajuste_kcal) }} kcal
        </span>
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

      <!-- Skipped: show a note instead of the planned items -->
      <p v-else-if="mealLog.saltada" class="skipped-note">
        Comida saltada. Sus macros se han redistribuido entre el resto del día.
      </p>

      <!-- Pending with planned meal items: show planned items -->
      <div v-else-if="mealLog.meal?.meal_items" class="planned-wrap">
      <div class="planned-toolbar">
        <motion.button
          :while-press="isRegenerating ? undefined : tapSubtle"
          :disabled="isRegenerating"
          @click="handleRegenerate"
          class="btn-reroll"
          :class="{ 'btn-reroll--loading': isRegenerating }"
          title="Generar otra opción de plato"
        >
          <RefreshCw :size="14" :stroke-width="2" aria-hidden="true" />
          {{ isRegenerating ? 'Generando…' : 'Otra opción' }}
        </motion.button>
      </div>
      <ul class="food-list food-list--planned">
        <li v-for="item in mealLog.meal.meal_items" :key="item.id" class="food-item">
          <span class="food-name">
            {{ item.food?.nombre }}
            <span class="food-weight">({{ Math.round(item.cantidad_gramos) }}g)</span>
          </span>
          <motion.button
            v-if="!isAllergic(item.food_id)"
            :while-press="tapSubtle"
            @click="emit('substitute', item)"
            class="btn-substitute"
          >
            <RefreshCw :size="14" :stroke-width="2" aria-hidden="true" />
            Sustituir
          </motion.button>
        </li>
      </ul>
      </div>
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
        <!-- Pending: complete or skip directly -->
        <template v-if="!mealLog.realizada && !mealLog.saltada">
          <motion.button
            :while-press="tap"
            @click="emit('complete', mealLog.id)"
            class="btn-action btn-action--complete"
          >
            Realizada
          </motion.button>
          <motion.button
            :while-press="tap"
            @click="emit('skip', mealLog.id)"
            class="btn-action btn-action--skip"
          >
            Saltar
          </motion.button>
        </template>

        <!-- Completed: indicator + undo -->
        <div v-else-if="mealLog.realizada" class="state-actions-wrap">
          <motion.span
            class="completed-indicator"
            :initial="{ scale: 0.6, opacity: 0 }"
            :animate="{ scale: 1, opacity: 1 }"
            :transition="spring.bouncy"
          ><CircleCheck :size="14" :stroke-width="2" aria-hidden="true" /> Realizada</motion.span>
          <motion.button :while-press="tap" @click="emit('reset', mealLog.id)" class="btn-action btn-action--undo">
            Deshacer
          </motion.button>
        </div>

        <!-- Skipped: indicator + undo -->
        <div v-else-if="mealLog.saltada" class="state-actions-wrap">
          <motion.span
            class="skipped-indicator"
            :initial="{ scale: 0.6, opacity: 0 }"
            :animate="{ scale: 1, opacity: 1 }"
            :transition="spring.snappy"
          ><CircleSlash :size="14" :stroke-width="2" aria-hidden="true" /> Saltada</motion.span>
          <motion.button :while-press="tap" @click="emit('reset', mealLog.id)" class="btn-action btn-action--undo">
            Deshacer
          </motion.button>
        </div>
      </div>
    </div>
  </motion.div>
</template>

<style scoped>
.meal-card {
  background: var(--color-surface);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  transition: all 0.25s ease;
}

.meal-card--completed {
  border-color: rgba(22, 163, 74, 0.3);
  box-shadow: var(--shadow-md);
}

.meal-card--skipped {
  border-style: dashed;
  border-color: var(--border-strong);
  opacity: 0.75;
}

.meal-card--extra {
  border-color: rgba(37, 99, 235, 0.2);
}

.meal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.meal-title-wrap {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.meal-name {
  font-size: var(--fs-lg);
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
}

.badge-extra, .badge-pre, .badge-post {
  font-size: var(--fs-xs);
  font-weight: 600;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-pill);
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

.badge-ajuste {
  font-size: var(--fs-xs);
  font-weight: 600;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-pill);
  background-color: rgba(37, 99, 235, 0.1);
  color: var(--color-system);
}

.meal-time {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
}

.time-real {
  color: var(--color-protein);
  font-weight: 500;
}

.meal-body {
  border-top: 1px solid var(--border-subtle);
  border-bottom: 1px solid var(--border-subtle);
  padding: var(--space-3) 0;
}

.food-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.planned-wrap {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.planned-toolbar {
  display: flex;
  justify-content: flex-end;
}

.btn-reroll {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-1) var(--space-2);
  background: var(--color-bg);
  border: 1px solid var(--border-default);
  color: var(--color-system);
  font-size: var(--fs-xs);
  font-weight: 600;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-reroll:hover:not(:disabled) {
  background-color: rgba(37, 99, 235, 0.08);
}

.btn-reroll--loading {
  opacity: 0.6;
  cursor: progress;
}

.food-list--planned {
  opacity: 0.75;
}

.food-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: var(--fs-sm);
}

.food-name {
  color: var(--color-text);
}

.food-weight {
  color: var(--color-text-muted);
  font-weight: 500;
}

.empty-food {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
  font-style: italic;
  margin: 0;
}

.meal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-4);
}

.macros-summary {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  font-size: var(--fs-sm);
  font-weight: 600;
}

.macro-dot {
  width: 6px;
  height: 6px;
  border-radius: var(--radius-full);
  display: inline-block;
  margin-right: var(--space-1);
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

.meal-actions {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.state-actions-wrap {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.skipped-indicator {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: var(--fs-sm);
  font-weight: 600;
  color: var(--color-text-muted);
}

.skipped-note {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
  font-style: italic;
}

.completed-indicator {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: var(--fs-sm);
  font-weight: 600;
  color: var(--color-protein);
}

.btn-action {
  font-size: var(--fs-sm);
  font-weight: 600;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
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
  border-color: var(--border-strong);
  color: var(--color-text-muted);
}

.btn-action--skip:hover {
  background-color: rgba(138, 129, 120, 0.05);
  border-color: var(--border-strong);
  color: var(--color-text);
}

.btn-action--undo {
  background-color: transparent;
  border-color: var(--border-strong);
  color: var(--color-text-muted);
}

.btn-action--undo:hover {
  background-color: rgba(138, 129, 120, 0.05);
  border-color: var(--border-strong);
  color: var(--color-text);
}

.btn-substitute {
  background: none;
  border: none;
  font-size: var(--fs-xs);
  font-weight: 600;
  color: var(--color-system);
  cursor: pointer;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-sm);
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
}

.btn-substitute:hover {
  background-color: rgba(37, 99, 235, 0.08);
}
</style>
