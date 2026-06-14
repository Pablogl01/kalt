<template>
  <div class="auth-page">
    <div class="auth-card">
      <!-- Logo -->
      <div class="auth-logo">
        <span class="logo-text">KALT</span>
        <span class="logo-sub">Nutrición deportiva</span>
      </div>

      <!-- Heading -->
      <h1 class="auth-title">Iniciar sesión</h1>
      <p class="auth-subtitle">Bienvenido de vuelta</p>

      <!-- Error banner -->
      <div v-if="errorMessage" class="auth-error" role="alert" aria-live="polite">
        {{ errorMessage }}
      </div>

      <!-- Form -->
      <form id="login-form" @submit.prevent="handleLogin" novalidate>
        <!-- Email -->
        <div class="field">
          <label class="field-label" for="login-email">Email</label>
          <input
            id="login-email"
            v-model="form.email"
            type="email"
            class="field-input"
            :class="{ 'field-input--error': fieldErrors.email }"
            autocomplete="email"
            placeholder="tu@email.com"
            required
          />
          <p v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</p>
        </div>

        <!-- Password -->
        <div class="field">
          <label class="field-label" for="login-password">Contraseña</label>
          <input
            id="login-password"
            v-model="form.password"
            type="password"
            class="field-input"
            :class="{ 'field-input--error': fieldErrors.password }"
            autocomplete="current-password"
            placeholder="••••••••"
            required
          />
          <p v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</p>
        </div>

        <!-- Submit -->
        <button
          id="login-submit"
          type="submit"
          class="btn-primary"
          :disabled="loading"
        >
          <span v-if="loading" class="btn-spinner" aria-hidden="true"></span>
          {{ loading ? 'Entrando…' : 'Iniciar sesión' }}
        </button>
      </form>

      <!-- Footer link -->
      <p class="auth-footer">
        ¿No tienes cuenta?
        <RouterLink id="link-to-register" to="/register" class="auth-link">Crear cuenta</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/stores/userStore'

const router    = useRouter()
const route     = useRoute()
const userStore = useUserStore()

const loading      = ref(false)
const errorMessage = ref('')
const fieldErrors  = reactive({ email: '', password: '' })

const form = reactive({
  email:    '',
  password: '',
})

async function handleLogin() {
  loading.value      = false
  errorMessage.value = ''
  fieldErrors.email    = ''
  fieldErrors.password = ''
  loading.value = true

  try {
    await userStore.login(form.email, form.password)
    const redirect = route.query.redirect || '/profile'
    router.push(redirect)
  } catch (err) {
    if (err.response?.status === 422) {
      const errors = err.response.data?.errors ?? {}
      fieldErrors.email    = errors.email?.[0]    ?? ''
      fieldErrors.password = errors.password?.[0] ?? ''
      if (!fieldErrors.email && !fieldErrors.password) {
        errorMessage.value = 'Credenciales incorrectas. Inténtalo de nuevo.'
      }
    } else {
      errorMessage.value = 'Error de conexión. Por favor, inténtalo más tarde.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────── */
.auth-page {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg);
  padding: 1.5rem;
}

.auth-card {
  width: 100%;
  max-width: 420px;
  background: var(--color-surface);
  border-radius: 20px;
  padding: 2.5rem 2rem;
  box-shadow:
    0 4px 6px -1px rgba(31, 27, 22, 0.06),
    0 12px 40px -8px rgba(31, 27, 22, 0.12);
}

/* ── Logo ───────────────────────────────────────────────── */
.auth-logo {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.125rem;
  margin-bottom: 2rem;
}

.logo-text {
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-accent-dark);
  letter-spacing: -0.03em;
  line-height: 1;
}

.logo-sub {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

/* ── Headings ───────────────────────────────────────────── */
.auth-title {
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 0.25rem;
  text-align: center;
}

.auth-subtitle {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  text-align: center;
  margin: 0 0 1.75rem;
}

/* ── Error banner ───────────────────────────────────────── */
.auth-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  margin-bottom: 1.25rem;
  text-align: center;
}

/* ── Fields ─────────────────────────────────────────────── */
.field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  margin-bottom: 1.125rem;
}

.field-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
}

.field-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1.5px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-bg);
  color: var(--color-text);
  font-family: var(--font-sans);
  font-size: 0.9375rem;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  box-sizing: border-box;
}

.field-input::placeholder {
  color: var(--color-text-muted);
}

.field-input:focus {
  border-color: var(--color-accent-dark);
  box-shadow: 0 0 0 3px rgba(168, 224, 99, 0.25);
}

.field-input--error {
  border-color: #f87171;
}

.field-input--error:focus {
  box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.2);
}

.field-error {
  font-size: 0.8125rem;
  color: #dc2626;
  margin: 0;
}

/* ── Button ─────────────────────────────────────────────── */
.btn-primary {
  width: 100%;
  padding: 0.8125rem 1.5rem;
  background: var(--color-accent);
  color: var(--color-text);
  font-family: var(--font-sans);
  font-size: 0.9375rem;
  font-weight: 700;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  margin-top: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: background 0.15s ease, transform 0.1s ease;
}

.btn-primary:hover:not(:disabled) {
  background: var(--color-accent-dark);
}

.btn-primary:active:not(:disabled) {
  transform: scale(0.98);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(31, 27, 22, 0.3);
  border-top-color: var(--color-text);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  flex-shrink: 0;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ── Footer ─────────────────────────────────────────────── */
.auth-footer {
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted);
  margin: 1.5rem 0 0;
}

.auth-link {
  color: var(--color-accent-dark);
  font-weight: 600;
  text-decoration: none;
  transition: color 0.15s;
}

.auth-link:hover {
  color: var(--color-text);
}
</style>
