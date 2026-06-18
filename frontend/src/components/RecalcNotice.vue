<!-- RecalcNotice.vue
     Persistent contextual notice explaining why macros were recalculated.
     It stays until the user undoes the change, closes it, or another action
     replaces it (no auto-dismiss). -->
<script setup>
import { RefreshCw } from 'lucide-vue-next'

const props = defineProps({
  message: {
    type: String,
    required: true
  },
  // Whether the recalculation can be reverted (a snapshot exists).
  undoable: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['close', 'undo'])
</script>

<template>
  <div class="recalc-notice" role="status" aria-live="polite">
    <div class="notice-icon"><RefreshCw :size="18" :stroke-width="2" aria-hidden="true" /></div>
    <div class="notice-content">
      <p class="notice-text">{{ message }}</p>
    </div>
    <button v-if="undoable" class="undo-btn" @click="emit('undo')">Deshacer</button>
    <button class="close-btn" @click="emit('close')" aria-label="Cerrar aviso">&times;</button>
  </div>
</template>

<style scoped>
.recalc-notice {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  background-color: rgba(37, 99, 235, 0.08); /* --color-system at 8% opacity */
  border: 1px solid rgba(37, 99, 235, 0.2);
  border-radius: var(--radius-md);
  padding: var(--space-3) var(--space-4);
  margin: var(--space-4) 0;
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
  font-size: var(--fs-sm);
  font-weight: 500;
  color: var(--color-text);
  margin: 0;
  line-height: 1.4;
}

.undo-btn {
  flex-shrink: 0;
  background: none;
  border: 1px solid var(--color-system);
  color: var(--color-system);
  font-family: inherit;
  font-size: var(--fs-xs);
  font-weight: 600;
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.undo-btn:hover {
  background-color: rgba(37, 99, 235, 0.08);
}

.close-btn {
  background: none;
  border: none;
  font-size: var(--fs-lg);
  font-weight: bold;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 0 var(--space-1);
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
