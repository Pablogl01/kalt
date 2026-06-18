import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/client'

export const useLogStore = defineStore('log', () => {
  const dailyLog = ref(null)
  const mealLogs = ref([])

  function setDailyLog(log) {
    dailyLog.value = log
  }

  function setMealLogs(logs) {
    mealLogs.value = logs
  }

  async function fetchDailyLog(fecha) {
    try {
      const { data } = await api.get(`/daily-logs/${fecha}`)
      setDailyLog(data)
      setMealLogs(data.meal_logs || [])
      return data
    } catch (err) {
      console.error('Error fetching daily log:', err)
      throw err
    }
  }

  async function updateTraining(dailyLogId, haEntrenado, horaGimnasio = null) {
    try {
      const { data } = await api.patch(`/daily-logs/${dailyLogId}/training`, {
        ha_entrenado: haEntrenado,
        hora_gimnasio: horaGimnasio
      })
      setDailyLog(data)
      setMealLogs(data.meal_logs || [])
      return data
    } catch (err) {
      console.error('Error updating training status:', err)
      throw err
    }
  }

  async function completeMeal(mealLogId) {
    try {
      const { data } = await api.patch(`/meal-logs/${mealLogId}/complete`)
      const index = mealLogs.value.findIndex(l => l.id === mealLogId)
      if (index !== -1) {
        mealLogs.value[index] = data
      }
      return data
    } catch (err) {
      console.error('Error completing meal:', err)
      throw err
    }
  }

  async function skipMeal(mealLogId) {
    try {
      const { data } = await api.patch(`/meal-logs/${mealLogId}/skip`)
      const mensaje = data.mensaje_recalculo
      // A skip rescales the remaining meals, so refresh the whole day to pick up
      // their new quantities and the persistent "ajustado" marks.
      if (dailyLog.value?.fecha) {
        await fetchDailyLog(dailyLog.value.fecha)
      }
      return { ...data, mensaje_recalculo: mensaje }
    } catch (err) {
      console.error('Error skipping meal:', err)
      throw err
    }
  }

  async function undoRecalc(dailyLogId) {
    try {
      // Reverts the last automatic recalculation; returns the refreshed day.
      const { data } = await api.post(`/daily-logs/${dailyLogId}/recalc/undo`)
      setDailyLog(data)
      setMealLogs(data.meal_logs || [])
      return data
    } catch (err) {
      console.error('Error undoing recalculation:', err)
      throw err
    }
  }

  async function resetMeal(mealLogId) {
    try {
      // Returns the full DailyLog: a reset can restore several meals (undo of
      // a skip's redistribution), so we replace the whole day state.
      const { data } = await api.patch(`/meal-logs/${mealLogId}/reset`)
      setDailyLog(data)
      setMealLogs(data.meal_logs || [])
      return data
    } catch (err) {
      console.error('Error resetting meal:', err)
      throw err
    }
  }

  async function addExtraMeal(dailyLogId, items) {
    try {
      const { data } = await api.post(`/meal-logs/${dailyLogId}/extra`, { items })
      // Refresh the daily log to ensure everything is synced and recalculations are loaded
      if (dailyLog.value) {
        await fetchDailyLog(dailyLog.value.fecha)
      }
      return data
    } catch (err) {
      console.error('Error adding extra meal:', err)
      throw err
    }
  }

  return {
    dailyLog,
    mealLogs,
    setDailyLog,
    setMealLogs,
    fetchDailyLog,
    updateTraining,
    completeMeal,
    skipMeal,
    resetMeal,
    undoRecalc,
    addExtraMeal
  }
})
