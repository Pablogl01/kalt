<script setup>
import { onMounted, onUnmounted, watch } from 'vue'
import { TriangleAlert } from 'lucide-vue-next'
import { useDietStore } from '@/stores/dietStore'

const props = defineProps({
  planId: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['plan-ready', 'plan-failed'])
const dietStore = useDietStore()

onMounted(() => {
  if (props.planId) {
    dietStore.startPolling(props.planId)
  }
})

onUnmounted(() => {
  dietStore.stopPolling()
})

watch(() => dietStore.planStatus, (status) => {
  if (status === 'ready') {
    emit('plan-ready')
  } else if (status === 'failed') {
    emit('plan-failed', dietStore.errorMessage)
  }
})

function handleRetry() {
  dietStore.generatePlan()
}
</script>

<template>
  <div class="poller-container">
    <div v-if="dietStore.planStatus === 'pending'" class="poller-state poller-state--loading">
      <div class="spinner"></div>
      <h3 class="poller-title">Generando tu plan de alimentación...</h3>
      <p class="poller-desc">Esto puede tardar unos segundos. Estamos calculando tus macros y seleccionando los mejores alimentos para tu objetivo.</p>
    </div>

    <div v-else-if="dietStore.planStatus === 'failed'" class="poller-state poller-state--failed">
      <div class="error-icon"><TriangleAlert :size="48" :stroke-width="2" aria-hidden="true" /></div>
      <h3 class="poller-title">No pudimos generar tu plan</h3>
      <p class="poller-desc">{{ dietStore.errorMessage || 'Hubo un problema procesando tu dieta. Por favor, vuelve a intentarlo.' }}</p>
      <button class="btn-retry" @click="handleRetry" :disabled="dietStore.isGenerating">
        {{ dietStore.isGenerating ? 'Solicitando...' : 'Reintentar generación' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.poller-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 300px;
  width: 100%;
}

.poller-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  max-width: 420px;
  padding: 2.5rem 2rem;
  background: var(--color-surface);
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(31, 27, 22, 0.08);
  border: 1px solid rgba(138, 129, 120, 0.1);
}

.spinner {
  width: 48px;
  height: 48px;
  border: 4px solid rgba(168, 224, 99, 0.2);
  border-top-color: var(--color-accent);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1.5rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-icon {
  display: flex;
  justify-content: center;
  color: var(--color-negative-badge);
  margin-bottom: 1rem;
}

.poller-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 0.75rem;
}

.poller-desc {
  font-size: 0.9375rem;
  color: var(--color-text-muted);
  line-height: 1.5;
  margin: 0 0 1.5rem;
}

.btn-retry {
  padding: 0.75rem 1.5rem;
  background: var(--color-accent);
  color: var(--color-text);
  font-weight: 700;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
}

.btn-retry:hover {
  background: var(--color-accent-dark);
}

.btn-retry:active {
  transform: scale(0.98);
}
</style>
