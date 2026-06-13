import { defineStore } from 'pinia'
import { ref } from 'vue'

// Phase 2 — WeeklyPlan + DayPlan state
export const useDietStore = defineStore('diet', () => {
  const weeklyPlan = ref(null)
  const currentDayPlan = ref(null)
  const planStatus = ref(null) // 'pending' | 'ready' | 'failed'

  function setPlan(plan) {
    weeklyPlan.value = plan
  }

  function setStatus(status) {
    planStatus.value = status
  }

  return { weeklyPlan, currentDayPlan, planStatus, setPlan, setStatus }
})
