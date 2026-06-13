import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export const useNavStore = defineStore('nav', () => {
  const router = useRouter()

  // Navigation items — shared between SidebarNav and BottomTabBar
  const navItems = [
    { name: 'dashboard',    label: 'Dashboard',       icon: 'home',          path: '/' },
    { name: 'weekly-plan',  label: 'Plan Semanal',    icon: 'calendar',      path: '/plan' },
    { name: 'daily-log',    label: 'Seguimiento',     icon: 'check-circle',  path: '/log' },
    { name: 'shopping',     label: 'Compra',          icon: 'shopping-cart', path: '/shopping' },
    { name: 'stats',        label: 'Estadísticas',    icon: 'bar-chart',     path: '/stats' },
  ]

  // Profile is separate (bottom of sidebar, hidden from bottom bar main items)
  const profileItem = { name: 'profile', label: 'Perfil', icon: 'user', path: '/profile' }

  const currentRoute = ref('dashboard')

  function setRoute(routeName) {
    currentRoute.value = routeName
    const item = [...navItems, profileItem].find(i => i.name === routeName)
    if (item) router.push(item.path)
  }

  const isActive = (routeName) => currentRoute.value === routeName

  return { navItems, profileItem, currentRoute, setRoute, isActive }
})
