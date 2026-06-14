import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/client'

export const useDietStore = defineStore('diet', () => {
  const activePlan = ref(null)
  const planStatus = ref(null) // 'pending' | 'ready' | 'failed'
  const isGenerating = ref(false)
  const errorMessage = ref('')
  let pollInterval = null

  async function generatePlan() {
    isGenerating.value = true
    planStatus.value = 'pending'
    errorMessage.value = ''
    try {
      const { data } = await api.post('/plans/generate')
      // Starts polling with the returned UUID
      startPolling(data.uuid)
      return data.uuid
    } catch (err) {
      planStatus.value = 'failed'
      isGenerating.value = false
      errorMessage.value = err.response?.data?.message || 'Error al solicitar la generación del plan.'
      throw err;
    }
  }

  async function loadActivePlan() {
    try {
      const { data } = await api.get('/plans/active')
      activePlan.value = data
      planStatus.value = 'ready'
    } catch (err) {
      if (err.response?.status === 404) {
        activePlan.value = null
      } else {
        console.error('Error loading active plan:', err)
      }
    }
  }

  function startPolling(uuid) {
    if (pollInterval) {
      clearInterval(pollInterval)
    }

    pollInterval = setInterval(async () => {
      try {
        const { data } = await api.get(`/plans/${uuid}/status`)
        planStatus.value = data.status

        if (data.status === 'ready') {
          stopPolling()
          isGenerating.value = false
          await loadActivePlan()
        } else if (data.status === 'failed') {
          stopPolling()
          isGenerating.value = false
          errorMessage.value = data.error_message || 'Error desconocido durante la generación.'
        }
      } catch (err) {
        console.error('Error polling plan status:', err)
        // If we get an error response, we can stop polling and set failed
        stopPolling()
        isGenerating.value = false
        planStatus.value = 'failed'
        errorMessage.value = 'Se perdió la conexión al verificar el estado.'
      }
    }, 2000)
  }

  function stopPolling() {
    if (pollInterval) {
      clearInterval(pollInterval)
      pollInterval = null
    }
  }

  return {
    activePlan,
    planStatus,
    isGenerating,
    errorMessage,
    generatePlan,
    loadActivePlan,
    startPolling,
    stopPolling,
  }
})
