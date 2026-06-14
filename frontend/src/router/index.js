import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/stores/userStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // ── Auth (public) ─────────────────────────────────────
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/Auth/Login.vue'),
      meta: { requiresAuth: false, label: 'Iniciar sesión' },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/Auth/Register.vue'),
      meta: { requiresAuth: false, label: 'Crear cuenta' },
    },

    // ── App (protected) ───────────────────────────────────
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/Dashboard.vue'),
      meta: { requiresAuth: true, label: 'Dashboard', icon: 'home' },
    },
    {
      path: '/plan',
      name: 'weekly-plan',
      component: () => import('@/views/WeeklyPlan.vue'),
      meta: { requiresAuth: true, label: 'Plan Semanal', icon: 'calendar' },
    },
    {
      path: '/log',
      name: 'daily-log',
      component: () => import('@/views/DailyLog.vue'),
      meta: { requiresAuth: true, label: 'Seguimiento', icon: 'check-circle' },
    },
    {
      path: '/shopping',
      name: 'shopping',
      component: () => import('@/views/Shopping.vue'),
      meta: { requiresAuth: true, label: 'Compra', icon: 'shopping-cart' },
    },
    {
      path: '/stats',
      name: 'stats',
      component: () => import('@/views/Stats.vue'),
      meta: { requiresAuth: true, label: 'Estadísticas', icon: 'bar-chart' },
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/Profile.vue'),
      meta: { requiresAuth: true, label: 'Perfil', icon: 'user' },
    },
    {
      path: '/onboarding',
      name: 'onboarding',
      component: () => import('@/views/Onboarding.vue'),
      meta: { requiresAuth: false, label: 'Onboarding' },
    },
  ],
})

// ── Navigation guard ──────────────────────────────────────
router.beforeEach(async (to) => {
  const userStore = useUserStore()

  // If auth state is unknown and we haven't attempted to restore yet,
  // try to restore session from the httpOnly cookie (silent 401 = not logged in)
  if (!userStore.isAuthenticated && to.meta.requiresAuth) {
    const restored = await userStore.tryRestoreSession()
    if (!restored) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }
  }

  // Redirect authenticated users away from login/register
  if (!to.meta.requiresAuth && userStore.isAuthenticated && ['login', 'register'].includes(to.name)) {
    return { name: 'dashboard' }
  }
})

// Listen for unauthenticated events dispatched by the API client
window.addEventListener('kalt:unauthenticated', () => {
  const userStore = useUserStore()
  userStore.logout().catch(() => {})
  router.push({ name: 'login' })
})

export default router
