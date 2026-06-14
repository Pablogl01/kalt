import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/client'

export const useUserStore = defineStore('user', () => {
  // ── State ────────────────────────────────────────────────
  const user   = ref(null)
  const macros = ref(null) // { calorias, proteina, carbos, grasa } | null

  // ── Getters ──────────────────────────────────────────────
  const isAuthenticated      = computed(() => !!user.value)
  const hasCompleteProfile   = computed(() =>
    !!user.value?.sexo &&
    !!user.value?.peso &&
    !!user.value?.altura &&
    !!user.value?.edad &&
    !!user.value?.objetivo &&
    !!user.value?.nivel_actividad
  )

  // ── Internal helpers ─────────────────────────────────────
  function _applyProfileResponse({ user: userData, macros: macrosData }) {
    user.value   = userData
    macros.value = macrosData
  }

  // ── Auth actions ─────────────────────────────────────────

  /**
   * Register a new account and log in automatically.
   * On success the backend returns { user } (no macros yet — profile is empty).
   * Redirects to /profile.
   */
  async function register(name, email, password, passwordConfirmation) {
    const { data } = await api.post('/register', {
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    })
    user.value   = data.user
    macros.value = null
  }

  /**
   * Authenticate an existing account.
   * Loads the profile with macros immediately after login.
   */
  async function login(email, password) {
    await api.post('/login', { email, password })
    // Load full profile (including macros) in one extra call
    await fetchProfile()
  }

  /**
   * Destroy the authenticated session and clear local state.
   */
  async function logout() {
    await api.post('/logout')
    user.value   = null
    macros.value = null
  }

  // ── Profile actions ───────────────────────────────────────

  /**
   * Fetch the authenticated user's profile and macros.
   * Called on login and on initial app bootstrap.
   */
  async function fetchProfile() {
    const { data } = await api.get('/profile')
    _applyProfileResponse(data)
  }

  /**
   * Update the user's profile fields and refresh macros.
   */
  async function updateProfile(profileData) {
    const { data } = await api.put('/profile', profileData)
    _applyProfileResponse(data)
  }

  // ── Session persistence ───────────────────────────────────

  /**
   * Attempt to restore session from the cookie on app start.
   * Returns true if the user is logged in, false otherwise.
   */
  async function tryRestoreSession() {
    try {
      await fetchProfile()
      return true
    } catch {
      user.value   = null
      macros.value = null
      return false
    }
  }

  return {
    // state
    user,
    macros,
    // getters
    isAuthenticated,
    hasCompleteProfile,
    // actions
    register,
    login,
    logout,
    fetchProfile,
    updateProfile,
    tryRestoreSession,
  }
})
