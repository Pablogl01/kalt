<!-- PlanStatusPoller.vue — Phase 2
     Polls GET /api/plans/{id}/status every 2s until status = ready or failed -->
<script setup>
defineProps({ planId: String })
defineEmits(['ready', 'failed'])
</script>

<template>
  <div class="poller-placeholder">
    <p style="color: var(--color-text-muted); font-size: 0.75rem;">PlanStatusPoller — Phase 2</p>
  </div>
</template>
