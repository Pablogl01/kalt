<script setup>
import { onMounted, onBeforeUnmount, watch, ref } from 'vue'
import Chart from 'chart.js/auto'

const props = defineProps({
  logs: {
    type: Array,
    required: true
  }
})

const canvasRef = ref(null)
let chartInstance = null

const initChart = () => {
  if (chartInstance) {
    chartInstance.destroy()
  }

  if (!canvasRef.value || props.logs.length === 0) return

  // Sort logs by date ascending for line chart
  const sortedLogs = [...props.logs].sort((a, b) => a.fecha.localeCompare(b.fecha))

  const labels = sortedLogs.map(log => {
    const parts = log.fecha.split('-')
    const date = new Date(parts[0], parts[1] - 1, parts[2])
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })
  })
  
  const dataPoints = sortedLogs.map(log => log.peso)

  chartInstance = new Chart(canvasRef.value, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Evolución de Peso (kg)',
        data: dataPoints,
        borderColor: '#A8E063', // --color-accent
        backgroundColor: 'rgba(168, 224, 99, 0.1)',
        borderWidth: 2,
        tension: 0.3,
        fill: true,
        pointBackgroundColor: '#A8E063',
        pointRadius: 4,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: '#1C1A17',
          titleColor: '#FFFFFF',
          bodyColor: '#FFFFFF',
          borderColor: '#374151',
          borderWidth: 1,
          padding: 10,
          callbacks: {
            title: (context) => {
              const index = context[0].dataIndex
              const item = sortedLogs[index]
              if (!item) return ''
              const parts = item.fecha.split('-')
              const date = new Date(parts[0], parts[1] - 1, parts[2])
              return date.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
            },
            label: (context) => {
              const index = context.dataIndex
              const item = sortedLogs[index]
              let text = ` Peso: ${context.parsed.y} kg`
              if (item && item.nota) {
                text += ` (${item.nota})`
              }
              return text
            }
          }
        }
      },
      scales: {
        x: {
          grid: {
            color: 'rgba(138, 129, 120, 0.1)'
          },
          ticks: {
            color: '#8A8178',
            font: {
              family: 'Plus Jakarta Sans',
              size: 11
            }
          }
        },
        y: {
          grid: {
            color: 'rgba(138, 129, 120, 0.1)'
          },
          ticks: {
            color: '#8A8178',
            font: {
              family: 'Plus Jakarta Sans',
              size: 11
            }
          }
        }
      }
    }
  })
}

watch(() => props.logs, () => {
  initChart()
}, { deep: true })

onMounted(() => {
  initChart()
})

onBeforeUnmount(() => {
  if (chartInstance) {
    chartInstance.destroy()
  }
})
</script>

<template>
  <div class="chart-container">
    <canvas ref="canvasRef"></canvas>
  </div>
</template>

<style scoped>
.chart-container {
  position: relative;
  width: 100%;
  height: 100%;
}
</style>
