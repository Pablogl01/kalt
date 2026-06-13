<!-- SubstituteSelector.vue — Phase 4
     Unified food substitution component used in plan, log and shopping views.
     IMPORTANT: backend always validates restrictions — this component only presents options. -->
<script setup>
defineProps({ foodId: String, context: String })
defineEmits(['selected'])
</script>

<template>
  <div class="substitute-selector-placeholder">
    <p style="color: var(--color-text-muted); font-size: 0.75rem;">SubstituteSelector — Phase 4</p>
  </div>
</template>
