<!-- RecalcNotice.vue — Phase 4
     Non-intrusive contextual notice explaining why macros were recalculated. -->
<script setup>
import { onMounted, onUnmounted } from 'vue'
import { RefreshCw } from 'lucide-vue-next'

const props = defineProps({
  message: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close'])

let timeoutId = null

onMounted(() => {
  timeoutId = setTimeout(() => {
    emit('close')
  }, 6000)
})

onUnmounted(() => {
  if (timeoutId) {
    clearTimeout(timeoutId)
  }
})
</script>

<template>
  <div class="recalc-notice">
    <div class="notice-icon"><RefreshCw :size="18" :stroke-width="2" aria-hidden="true" /></div>
    <div class="notice-content">
      <p class="notice-text">{{ message }}</p>
    </div>
    <button class="close-btn" @click="emit('close')" aria-label="Cerrar notice">&times;</button>
  </div>
</template>

<style scoped>
.recalc-notice {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background-color: rgba(37, 99, 235, 0.08); /* --color-system at 8% opacity */
  border: 1px solid rgba(37, 99, 235, 0.2);
  border-radius: 12px;
  padding: 0.75rem 1rem;
  margin: 1rem 0;
  animation: slideIn 0.3s ease-out;
  position: relative;
}

.notice-icon {
  display: inline-flex;
  align-items: center;
  color: var(--color-system);
}

.notice-content {
  flex: 1;
}

.notice-text {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--color-text);
  margin: 0;
  line-height: 1.4;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  font-weight: bold;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 0 0.25rem;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.2s ease;
}

.close-btn:hover {
  color: var(--color-text);
}

@keyframes slideIn {
  from {
    transform: translateY(-10px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}
</style>
