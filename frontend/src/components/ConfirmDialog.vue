<script setup>
// Reusable confirmation dialog, styled with the KALT design system.
// The parent controls visibility via the `open` prop and reacts to events.
import { motion, AnimatePresence } from 'motion-v'
import { backdrop, modalCard, tap, tapSubtle } from '@/lib/motion'

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Confirmar' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirmar' },
  cancelLabel: { type: String, default: 'Cancelar' },
})

const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <Teleport to="body">
    <AnimatePresence>
      <motion.div
        v-if="open"
        key="cd-overlay"
        class="cd-overlay"
        :variants="backdrop"
        initial="hidden"
        animate="show"
        exit="exit"
        @click.self="emit('cancel')"
        @keydown.esc="emit('cancel')"
      >
        <motion.div
          class="cd-card"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
          :variants="modalCard"
          initial="hidden"
          animate="show"
          exit="exit"
        >
          <h3 class="cd-title">{{ title }}</h3>
          <p v-if="message" class="cd-message">{{ message }}</p>
          <div class="cd-actions">
            <motion.button :while-press="tapSubtle" type="button" class="cd-btn cd-btn--secondary" @click="emit('cancel')">
              {{ cancelLabel }}
            </motion.button>
            <motion.button :while-press="tap" type="button" class="cd-btn cd-btn--primary" autofocus @click="emit('confirm')">
              {{ confirmLabel }}
            </motion.button>
          </div>
        </motion.div>
      </motion.div>
    </AnimatePresence>
  </Teleport>
</template>

<style scoped>
.cd-overlay {
  position: fixed;
  inset: 0;
  background: rgba(31, 27, 22, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  z-index: 1000;
}

.cd-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  width: 100%;
  max-width: 360px;
  box-shadow: var(--shadow-lg);
}

.cd-title {
  font-size: var(--fs-lg);
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 var(--space-2);
}

.cd-message {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
  line-height: 1.5;
  margin: 0 0 var(--space-5);
}

.cd-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
}

.cd-btn {
  font-family: inherit;
  font-size: var(--fs-sm);
  font-weight: 600;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  cursor: pointer;
  border: 1px solid transparent;
  transition: background-color 0.2s ease;
}

.cd-btn--secondary {
  background: transparent;
  border-color: var(--border-strong);
  color: var(--color-text-muted);
}

.cd-btn--secondary:hover {
  background: rgba(138, 129, 120, 0.08);
  color: var(--color-text);
}

.cd-btn--primary {
  background-color: var(--color-accent);
  color: var(--color-text);
}

.cd-btn--primary:hover {
  background-color: var(--color-accent-dark);
}
</style>
