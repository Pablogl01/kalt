<script setup>
import { onMounted, onUnmounted, watch } from 'vue'
import { motion, AnimatePresence } from 'motion-v'
import { TriangleAlert } from 'lucide-vue-next'
import { useDietStore } from '@/stores/dietStore'
import { spring, sheet } from '@/lib/motion'

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
    <AnimatePresence mode="wait">
      <motion.div
        v-if="dietStore.planStatus === 'pending'"
        key="loading"
        class="poller-state poller-state--loading"
        :variants="sheet"
        initial="hidden"
        animate="show"
        exit="exit"
      >
        <div class="spinner"></div>
        <h3 class="poller-title">Generando tu plan de alimentación</h3>
        <p class="poller-desc">Estamos calculando tus macros y seleccionando los mejores alimentos para tu objetivo.</p>
        <div class="load-dots" aria-hidden="true">
          <motion.span
            v-for="i in 3"
            :key="i"
            class="load-dot"
            :animate="{ opacity: [0.25, 1, 0.25], y: [0, -5, 0] }"
            :transition="{ duration: 0.9, repeat: Infinity, ease: 'easeInOut', delay: (i - 1) * 0.15 }"
          />
        </div>
      </motion.div>

      <motion.div
        v-else-if="dietStore.planStatus === 'failed'"
        key="failed"
        class="poller-state poller-state--failed"
        :variants="sheet"
        initial="hidden"
        animate="show"
        exit="exit"
      >
        <motion.div
          class="error-icon"
          :initial="{ scale: 0.6, opacity: 0 }"
          :animate="{ scale: 1, opacity: 1 }"
          :transition="spring.bouncy"
        ><TriangleAlert :size="48" :stroke-width="2" aria-hidden="true" /></motion.div>
        <h3 class="poller-title">No pudimos generar tu plan</h3>
        <p class="poller-desc">{{ dietStore.errorMessage || 'Hubo un problema procesando tu dieta. Por favor, vuelve a intentarlo.' }}</p>
        <motion.button :while-press="{ scale: 0.97 }" class="btn-retry" @click="handleRetry" :disabled="dietStore.isGenerating">
          {{ dietStore.isGenerating ? 'Solicitando...' : 'Reintentar generación' }}
        </motion.button>
      </motion.div>
    </AnimatePresence>
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
  padding: var(--space-6) var(--space-6);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  border: 1px solid var(--border-subtle);
}

.spinner {
  width: 48px;
  height: 48px;
  border: 4px solid rgba(255, 212, 0, 0.2);
  border-top-color: var(--color-accent);
  border-radius: var(--radius-full);
  animation: spin 1s linear infinite;
  margin-bottom: var(--space-5);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.load-dots {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-5);
}

.load-dot {
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
  background: var(--color-accent);
  display: inline-block;
}

.error-icon {
  display: flex;
  justify-content: center;
  color: var(--color-negative-badge);
  margin-bottom: var(--space-4);
}

.poller-title {
  font-size: var(--fs-lg);
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 var(--space-3);
}

.poller-desc {
  font-size: var(--fs-base);
  color: var(--color-text-muted);
  line-height: 1.5;
  margin: 0 0 var(--space-5);
}

.btn-retry {
  padding: var(--space-3) var(--space-5);
  background: var(--color-accent);
  color: var(--color-text);
  font-weight: 700;
  border: none;
  border-radius: var(--radius-md);
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
