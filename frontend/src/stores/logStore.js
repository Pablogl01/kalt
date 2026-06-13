import { defineStore } from 'pinia'
import { ref } from 'vue'

// Phase 3 — Daily tracking state
export const useLogStore = defineStore('log', () => {
  const dailyLog = ref(null)
  const mealLogs = ref([])

  function setDailyLog(log) {
    dailyLog.value = log
  }

  function setMealLogs(logs) {
    mealLogs.value = logs
  }

  return { dailyLog, mealLogs, setDailyLog, setMealLogs }
})
