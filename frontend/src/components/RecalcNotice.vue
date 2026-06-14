<!-- RecalcNotice.vue — Phase 3
     Non-intrusive contextual notice explaining why macros were recalculated. -->
<script setup>
defineProps({
  message: {
    type: String,
    required: true
  }
})
</script>

<template>
  <div class="recalc-notice">
    <div class="notice-icon">⚙️</div>
    <div class="notice-content">
      <p class="notice-text">{{ message }}</p>
    </div>
  </div>
</template>

<style scoped>
.recalc-notice {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background-color: rgba(59, 130, 246, 0.08); /* --color-system at 8% opacity */
  border: 1px solid rgba(59, 130, 246, 0.2);
  border-radius: 12px;
  padding: 0.75rem 1rem;
  margin: 1rem 0;
}

.notice-icon {
  font-size: 1.125rem;
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
</style>
