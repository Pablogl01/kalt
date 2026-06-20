<script setup>
/* Counts from its previous value up/down to the new one with an
   easeOut curve — the "metric ticks into place" feel of iOS dashboards.
   Plain rAF (no motion-v dependency) so it's cheap and predictable.
   Honours prefers-reduced-motion by snapping straight to the target. */
import { ref, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  value: { type: Number, default: 0 },
  duration: { type: Number, default: 600 }, // ms
})

const display = ref(props.value)
let raf = null

const prefersReduced =
  typeof window !== 'undefined' &&
  window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

// easeOutCubic — decelerating, matches the --ease-out token
const ease = (t) => 1 - Math.pow(1 - t, 3)

function animateTo(target) {
  cancelAnimationFrame(raf)

  if (prefersReduced) {
    display.value = target
    return
  }

  const startVal = display.value
  const delta = target - startVal
  if (delta === 0) return

  let startTime = null
  const step = (ts) => {
    if (startTime === null) startTime = ts
    const t = Math.min(1, (ts - startTime) / props.duration)
    display.value = startVal + delta * ease(t)
    if (t < 1) raf = requestAnimationFrame(step)
    else display.value = target
  }
  raf = requestAnimationFrame(step)
}

// Animate the first paint from zero so the metric "fills in" on load.
onMounted(() => {
  const target = props.value
  display.value = 0
  animateTo(target)
})

watch(() => props.value, (v) => animateTo(v))
onUnmounted(() => cancelAnimationFrame(raf))
</script>

<template>
  <span>{{ Math.round(display) }}</span>
</template>
