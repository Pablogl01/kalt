<script setup>
// Reusable confirmation dialog, styled with the KALT design system.
// The parent controls visibility via the `open` prop and reacts to events.
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
    <div
      v-if="open"
      class="cd-overlay"
      @click.self="emit('cancel')"
      @keydown.esc="emit('cancel')"
    >
      <div class="cd-card" role="dialog" aria-modal="true" :aria-label="title">
        <h3 class="cd-title">{{ title }}</h3>
        <p v-if="message" class="cd-message">{{ message }}</p>
        <div class="cd-actions">
          <button type="button" class="cd-btn cd-btn--secondary" @click="emit('cancel')">
            {{ cancelLabel }}
          </button>
          <button type="button" class="cd-btn cd-btn--primary" autofocus @click="emit('confirm')">
            {{ confirmLabel }}
          </button>
        </div>
      </div>
    </div>
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
  padding: 1rem;
  z-index: 1000;
}

.cd-card {
  background: var(--color-surface);
  border-radius: 16px;
  padding: 1.5rem;
  width: 100%;
  max-width: 360px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
}

.cd-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 0.5rem;
}

.cd-message {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  line-height: 1.5;
  margin: 0 0 1.25rem;
}

.cd-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.cd-btn {
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  padding: 0.5rem 1rem;
  border-radius: 10px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background-color 0.2s ease;
}

.cd-btn--secondary {
  background: transparent;
  border-color: rgba(138, 129, 120, 0.3);
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
