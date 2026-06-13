<!-- RecalcNotice.vue — Phase 3
     Non-intrusive contextual notice explaining why macros were recalculated.
     IMPORTANT: do not use v-html with user data -->
<script setup>
defineProps({ message: String })
</script>

<template>
  <div class="recalc-notice-placeholder">
    <p style="color: var(--color-text-muted); font-size: 0.75rem;">RecalcNotice — Phase 3</p>
  </div>
</template>
