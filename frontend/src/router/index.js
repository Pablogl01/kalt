import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/Dashboard.vue'),
      meta: { requiresAuth: true, label: 'Dashboard', icon: 'home' }
    },
    {
      path: '/plan',
      name: 'weekly-plan',
      component: () => import('@/views/WeeklyPlan.vue'),
      meta: { requiresAuth: true, label: 'Plan Semanal', icon: 'calendar' }
    },
    {
      path: '/log',
      name: 'daily-log',
      component: () => import('@/views/DailyLog.vue'),
      meta: { requiresAuth: true, label: 'Seguimiento', icon: 'check-circle' }
    },
    {
      path: '/shopping',
      name: 'shopping',
      component: () => import('@/views/Shopping.vue'),
      meta: { requiresAuth: true, label: 'Compra', icon: 'shopping-cart' }
    },
    {
      path: '/stats',
      name: 'stats',
      component: () => import('@/views/Stats.vue'),
      meta: { requiresAuth: true, label: 'Estadísticas', icon: 'bar-chart' }
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/Profile.vue'),
      meta: { requiresAuth: true, label: 'Perfil', icon: 'user' }
    },
    {
      path: '/onboarding',
      name: 'onboarding',
      component: () => import('@/views/Onboarding.vue'),
      meta: { requiresAuth: false, label: 'Onboarding' }
    },
  ]
})

export default router
