<!-- SubstituteSelector.vue — Phase 4
     Unified food substitution component used in plan, log and shopping views.
     IMPORTANT: backend always validates restrictions — this component only presents options. -->
<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/client'

const props = defineProps({
  mealItemId: {
    type: String,
    required: true
  },
  foodName: {
    type: String,
    required: true
  },
  context: {
    type: String,
    default: 'log' // 'plan' | 'log' | 'shopping'
  }
})

const emit = defineEmits(['selected', 'close'])

const substitutes = ref([])
const isLoading = ref(true)
const isSaving = ref(false)
const errorMessage = ref('')

onMounted(async () => {
  try {
    if (props.context === 'shopping') {
      const { data } = await api.patch(`/shopping-items/${props.mealItemId}/dont-want`)
      substitutes.value = data
    } else {
      const { data } = await api.get(`/meal-items/${props.mealItemId}/substitutes`)
      substitutes.value = data
    }
  } catch (err) {
    console.error('Failed to load substitutes:', err)
    errorMessage.value = 'No se pudieron cargar los sustitutos.'
  } finally {
    isLoading.value = false
  }
})

async function selectSubstitute(substituteId) {
  isSaving.value = true
  errorMessage.value = ''
  try {
    if (props.context === 'shopping') {
      const { data } = await api.patch(`/shopping-items/${props.mealItemId}/substitute`, {
        substitute_food_id: substituteId
      })
      emit('selected', data)
    } else {
      const { data } = await api.patch(`/meal-items/${props.mealItemId}/substitute`, {
        substitute_food_id: substituteId
      })
      emit('selected', data)
    }
    emit('close')
  } catch (err) {
    console.error('Failed to apply substitute:', err)
    errorMessage.value = err.response?.data?.message || 'Error al aplicar el sustituto.'
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="modal-overlay" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">Sustituir Alimento</h3>
        <button class="close-btn" @click="emit('close')" aria-label="Cerrar modal">&times;</button>
      </div>

      <div class="modal-body">
        <p class="original-food-label">
          Sustitutos válidos para: <span class="food-highlight">{{ foodName }}</span>
        </p>

        <div v-if="isLoading" class="loading-state">
          <div class="spinner"></div>
          <p>Buscando alternativas inteligentes...</p>
        </div>

        <div v-else-if="errorMessage" class="error-state">
          <p class="error-text">{{ errorMessage }}</p>
        </div>

        <div v-else-if="substitutes.length === 0" class="empty-state">
          <p>No hay sustitutos disponibles para este alimento.</p>
        </div>

        <div v-else class="substitutes-list">
          <div 
            v-for="sub in substitutes" 
            :key="sub.id" 
            class="substitute-card"
            :class="{ 'substitute-card--disabled': isSaving }"
            @click="!isSaving && selectSubstitute(sub.id)"
          >
            <div class="sub-card-header">
              <span class="sub-name">{{ sub.nombre }}</span>
              <span class="similarity-badge">
                {{ Math.round(sub.similitud_macros) }}% similitud
              </span>
            </div>

            <div class="macros-grid">
              <div class="macro-item">
                <span class="macro-val color-protein">{{ Math.round(sub.proteina) }}g</span>
                <span class="macro-label">Prot</span>
              </div>
              <div class="macro-item">
                <span class="macro-val color-carbs">{{ Math.round(sub.carbos) }}g</span>
                <span class="macro-label">Carb</span>
              </div>
              <div class="macro-item">
                <span class="macro-val color-fat">{{ Math.round(sub.grasa) }}g</span>
                <span class="macro-label">Grasa</span>
              </div>
              <div class="macro-item">
                <span class="macro-val color-cal">{{ Math.round(sub.calorias) }} kcal</span>
                <span class="macro-label">Calorías</span>
              </div>
            </div>
            
            <div class="card-action-indicator">
              <span>Seleccionar</span>
              <span class="arrow">→</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(28, 26, 23, 0.4);
  backdrop-filter: blur(8px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-card {
  background: var(--color-surface, #FFFFFF);
  border: 1px solid rgba(138, 129, 120, 0.2);
  border-radius: 20px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(138, 129, 120, 0.1);
}

.modal-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-text, #1C1A17);
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.75rem;
  font-weight: 300;
  color: var(--color-text-muted, #8A8178);
  cursor: pointer;
  padding: 0;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-body {
  padding: 1.5rem;
}

.original-food-label {
  font-size: 0.875rem;
  color: var(--color-text-muted, #8A8178);
  margin-top: 0;
  margin-bottom: 1.25rem;
}

.food-highlight {
  font-weight: 600;
  color: var(--color-text, #1C1A17);
}

.loading-state, .empty-state, .error-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: var(--color-text-muted, #8A8178);
}

.error-text {
  color: #DC2626;
  font-weight: 500;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(59, 130, 246, 0.1);
  border-top-color: var(--color-system, #3B82F6);
  border-radius: 50%;
  margin: 0 auto 1rem;
  animation: spin 0.8s linear infinite;
}

.substitutes-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-height: 350px;
  overflow-y: auto;
}

.substitute-card {
  background: #FAF8F5;
  border: 1px solid rgba(138, 129, 120, 0.15);
  border-radius: 12px;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.substitute-card:hover {
  background: var(--color-surface, #FFFFFF);
  border-color: var(--color-accent, #A8E063);
  box-shadow: 0 4px 12px rgba(168, 224, 99, 0.08);
}

.substitute-card--disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.sub-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.sub-name {
  font-weight: 600;
  color: var(--color-text, #1C1A17);
  font-size: 0.9375rem;
}

.similarity-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  background-color: rgba(59, 130, 246, 0.08);
  color: var(--color-system, #3B82F6);
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  border: 1px solid rgba(59, 130, 246, 0.15);
}

.macros-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.5rem;
  border-bottom: 1px solid rgba(138, 129, 120, 0.08);
  padding-bottom: 0.75rem;
  margin-bottom: 0.5rem;
}

.macro-item {
  display: flex;
  flex-direction: column;
}

.macro-val {
  font-size: 0.8125rem;
  font-weight: 700;
}

.macro-label {
  font-size: 0.6875rem;
  color: var(--color-text-muted, #8A8178);
  margin-top: 0.125rem;
}

.color-protein {
  color: var(--color-protein, #16A34A);
}

.color-carbs {
  color: var(--color-carbs, #EA580C);
}

.color-fat {
  color: var(--color-fat, #CA8A04);
}

.color-cal {
  color: var(--color-calories, #2563EB);
}

.card-action-indicator {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted, #8A8178);
  opacity: 0;
  transform: translateX(-5px);
  transition: all 0.2s ease;
}

.substitute-card:hover .card-action-indicator {
  opacity: 1;
  transform: translateX(0);
  color: var(--color-accent-hover, #6BA63D);
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@keyframes scaleUp {
  from {
    transform: scale(0.95);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
