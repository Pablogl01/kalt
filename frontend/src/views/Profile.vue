<template>
  <div class="profile-page">

    <!-- ── Hero: avatar + name + compact macros ───────────── -->
    <header class="profile-hero">
      <div class="hero-top">
        <div class="avatar" aria-hidden="true">{{ initials }}</div>
        <div class="hero-info">
          <h1 class="hero-name">{{ userStore.user?.name || 'Tu perfil' }}</h1>
          <span class="hero-goal">{{ objetivoLabel }}</span>
        </div>
      </div>

      <!-- Compact daily macros strip -->
      <div v-if="userStore.macros" class="hero-macros">
        <div class="hero-macro">
          <span class="hm-value">{{ Math.round(userStore.macros.calorias) }}</span>
          <span class="hm-label">kcal</span>
        </div>
        <div class="hero-macro">
          <span class="hm-value c-protein">{{ Math.round(userStore.macros.proteina) }}g</span>
          <span class="hm-label">Proteína</span>
        </div>
        <div class="hero-macro">
          <span class="hm-value c-carbs">{{ Math.round(userStore.macros.carbos) }}g</span>
          <span class="hm-label">Carbos</span>
        </div>
        <div class="hero-macro">
          <span class="hm-value c-fat">{{ Math.round(userStore.macros.grasa) }}g</span>
          <span class="hm-label">Grasa</span>
        </div>
      </div>
      <p v-else class="hero-macros-hint">
        <BarChart3 :size="16" :stroke-width="2" aria-hidden="true" />
        Completa tus datos para calcular tus macros diarios.
      </p>
    </header>

    <!-- ── Internal tabs ──────────────────────────────────── -->
    <nav class="profile-tabs" role="tablist" aria-label="Secciones del perfil">
      <button
        v-for="t in tabs"
        :key="t.id"
        type="button"
        role="tab"
        :aria-selected="activeTab === t.id"
        class="profile-tab"
        :class="{ 'profile-tab--active': activeTab === t.id }"
        @click="activeTab = t.id"
      >
        {{ t.label }}
      </button>
    </nav>

    <!-- Save feedback (shared across the form tabs) -->
    <div v-if="saveSuccess" class="save-banner save-banner--success" role="status" aria-live="polite">
      <Check :size="16" :stroke-width="2.5" aria-hidden="true" /> Perfil actualizado correctamente
    </div>
    <div v-if="saveError" class="save-banner save-banner--error" role="alert" aria-live="polite">
      {{ saveError }}
    </div>

    <!-- ── Tab: Datos ─────────────────────────────────────── -->
    <section v-show="activeTab === 'datos'" class="tab-panel" role="tabpanel">
      <form id="profile-form" @submit.prevent="handleSave" novalidate>
        <div class="field">
          <label class="field-label" for="profile-name">Nombre</label>
          <input id="profile-name" v-model="form.name" type="text" class="field-input"
            :class="{ 'field-input--error': fieldErrors.name }" autocomplete="name" placeholder="Tu nombre" />
          <p v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</p>
        </div>

        <div class="field">
          <label class="field-label" for="profile-email">Email</label>
          <input id="profile-email" v-model="form.email" type="email" class="field-input"
            :class="{ 'field-input--error': fieldErrors.email }" autocomplete="email" placeholder="tu@email.com" />
          <p v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</p>
        </div>

        <div class="field-row">
          <div class="field">
            <label class="field-label" for="profile-sexo">Sexo</label>
            <select id="profile-sexo" v-model="form.sexo" class="field-input field-select"
              :class="{ 'field-input--error': fieldErrors.sexo }">
              <option value="" disabled>Seleccionar…</option>
              <option value="hombre">Hombre</option>
              <option value="mujer">Mujer</option>
            </select>
            <p v-if="fieldErrors.sexo" class="field-error">{{ fieldErrors.sexo }}</p>
          </div>

          <div class="field">
            <label class="field-label" for="profile-edad">Edad</label>
            <input id="profile-edad" v-model.number="form.edad" type="number" min="10" max="120" class="field-input"
              :class="{ 'field-input--error': fieldErrors.edad }" placeholder="años" />
            <p v-if="fieldErrors.edad" class="field-error">{{ fieldErrors.edad }}</p>
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label class="field-label" for="profile-peso">Peso</label>
            <div class="field-unit-wrap">
              <input id="profile-peso" v-model.number="form.peso" type="number" min="20" max="500" step="0.1"
                class="field-input" :class="{ 'field-input--error': fieldErrors.peso }" placeholder="0" />
              <span class="field-unit">kg</span>
            </div>
            <p v-if="fieldErrors.peso" class="field-error">{{ fieldErrors.peso }}</p>
          </div>

          <div class="field">
            <label class="field-label" for="profile-altura">Altura</label>
            <div class="field-unit-wrap">
              <input id="profile-altura" v-model.number="form.altura" type="number" min="100" max="250"
                class="field-input" :class="{ 'field-input--error': fieldErrors.altura }" placeholder="0" />
              <span class="field-unit">cm</span>
            </div>
            <p v-if="fieldErrors.altura" class="field-error">{{ fieldErrors.altura }}</p>
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="profile-grasa-corporal">
            Grasa corporal <span class="field-label-optional">(opcional)</span>
          </label>
          <div class="field-unit-wrap">
            <input id="profile-grasa-corporal" v-model.number="form.grasa_corporal" type="number" min="1" max="60" step="0.1"
              class="field-input" :class="{ 'field-input--error': fieldErrors.grasa_corporal }" placeholder="0" />
            <span class="field-unit">%</span>
          </div>
          <p v-if="fieldErrors.grasa_corporal" class="field-error">{{ fieldErrors.grasa_corporal }}</p>
        </div>

        <button type="submit" class="btn-primary" :disabled="saving">
          <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
          {{ saving ? 'Guardando…' : 'Guardar cambios' }}
        </button>
      </form>
    </section>

    <!-- ── Tab: Objetivo ──────────────────────────────────── -->
    <section v-show="activeTab === 'objetivo'" class="tab-panel" role="tabpanel">
      <form @submit.prevent="handleSave" novalidate>
        <div class="field">
          <label class="field-label">Objetivo</label>
          <div class="radio-group" role="group" aria-label="Objetivo">
            <label v-for="opt in objetivoOptions" :key="opt.value" class="radio-card"
              :class="{ 'radio-card--active': form.objetivo === opt.value }">
              <input type="radio" :value="opt.value" v-model="form.objetivo" class="radio-hidden" />
              <span class="radio-icon" aria-hidden="true"><component :is="opt.icon" :size="24" :stroke-width="2" /></span>
              <span class="radio-label">{{ opt.label }}</span>
              <span class="radio-desc">{{ opt.desc }}</span>
            </label>
          </div>
          <p v-if="fieldErrors.objetivo" class="field-error">{{ fieldErrors.objetivo }}</p>
        </div>

        <div class="field">
          <label class="field-label" for="profile-actividad">Nivel de actividad</label>
          <select id="profile-actividad" v-model="form.nivel_actividad" class="field-input field-select"
            :class="{ 'field-input--error': fieldErrors.nivel_actividad }">
            <option value="" disabled>Seleccionar…</option>
            <option value="sedentario">Sedentario — Sin ejercicio o muy poco</option>
            <option value="ligero">Ligero — 1-3 días/semana</option>
            <option value="moderado">Moderado — 3-5 días/semana</option>
            <option value="alto">Alto — 6-7 días/semana o trabajo físico</option>
          </select>
          <p v-if="fieldErrors.nivel_actividad" class="field-error">{{ fieldErrors.nivel_actividad }}</p>
        </div>

        <div class="field">
          <label class="field-label">Días que entrenas</label>
          <div class="days-picker" role="group" aria-label="Días que entrenas">
            <button
              v-for="d in weekDays"
              :key="d.value"
              type="button"
              class="day-btn"
              :class="{ 'day-btn--active': form.dias_entreno.includes(d.value) }"
              :aria-pressed="form.dias_entreno.includes(d.value)"
              @click="toggleDay(d.value)"
            >
              {{ d.label }}
            </button>
          </div>
          <p v-if="fieldErrors.dias_entreno" class="field-error">{{ fieldErrors.dias_entreno }}</p>
        </div>

        <button type="submit" class="btn-primary" :disabled="saving">
          <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
          {{ saving ? 'Guardando…' : 'Guardar cambios' }}
        </button>
      </form>
    </section>

    <!-- ── Tab: Suplementos ───────────────────────────────── -->
    <section v-show="activeTab === 'suplementos'" class="tab-panel tab-panel--bare" role="tabpanel">
      <SupplementsConfig />
    </section>

    <!-- ── Logout ─────────────────────────────────────────── -->
    <div class="actions-section">
      <button type="button" id="profile-logout" class="btn-logout" @click="handleLogout">
        Cerrar sesión
      </button>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { BarChart3, Check, TrendingUp, Scale, TrendingDown } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/userStore'
import { useDietStore } from '@/stores/dietStore'
import SupplementsConfig from '@/components/SupplementsConfig.vue'

const router = useRouter()
const userStore = useUserStore()
const dietStore = useDietStore()

const tabs = [
  { id: 'datos', label: 'Datos' },
  { id: 'objetivo', label: 'Objetivo' },
  { id: 'suplementos', label: 'Suplementos' },
]
const activeTab = ref('datos')

const saving      = ref(false)
const saveSuccess = ref(false)
const saveError   = ref('')
const fieldErrors = reactive({
  name: '', email: '', sexo: '', edad: '', peso: '',
  altura: '', grasa_corporal: '', objetivo: '', nivel_actividad: '', dias_entreno: '',
})

const form = reactive({
  name: '', email: '', sexo: '', edad: '', peso: '',
  altura: '', grasa_corporal: '', objetivo: '', nivel_actividad: '',
  dias_entreno: [],
})

const objetivoOptions = [
  { value: 'volumen',       icon: TrendingUp,   label: 'Volumen',       desc: '+300 kcal' },
  { value: 'mantenimiento', icon: Scale,        label: 'Mantenimiento', desc: 'TDEE' },
  { value: 'definicion',    icon: TrendingDown, label: 'Definición',    desc: '−300 kcal' },
]

const weekDays = [
  { value: 1, label: 'L' }, { value: 2, label: 'M' }, { value: 3, label: 'X' },
  { value: 4, label: 'J' }, { value: 5, label: 'V' }, { value: 6, label: 'S' }, { value: 7, label: 'D' },
]

function toggleDay(value) {
  const i = form.dias_entreno.indexOf(value)
  if (i === -1) form.dias_entreno.push(value)
  else form.dias_entreno.splice(i, 1)
}

const initials = computed(() => {
  const n = (userStore.user?.name || '').trim()
  if (!n) return '–'
  return n.split(/\s+/).slice(0, 2).map(p => p[0]?.toUpperCase() || '').join('')
})

const objetivoLabel = computed(() =>
  objetivoOptions.find(o => o.value === userStore.user?.objetivo)?.label || 'Sin objetivo definido'
)

// Initialise form from store on mount
onMounted(() => {
  if (userStore.user) {
    Object.keys(form).forEach((key) => {
      form[key] = userStore.user[key] ?? ''
    })
    // dias_entreno must stay an array regardless of the generic copy above.
    form.dias_entreno = Array.isArray(userStore.user.dias_entreno)
      ? [...userStore.user.dias_entreno]
      : []
  }
})

async function handleSave() {
  saving.value      = true
  saveSuccess.value = false
  saveError.value   = ''
  Object.keys(fieldErrors).forEach((k) => (fieldErrors[k] = ''))

  // Build payload — omit empty optional fields
  const payload = {}
  Object.entries(form).forEach(([key, val]) => {
    if (val !== '' && val !== null && val !== undefined) {
      payload[key] = val
    }
  })

  try {
    const data = await userStore.updateProfile(payload)
    saveSuccess.value = true
    setTimeout(() => (saveSuccess.value = false), 3500)
    // Training-day changes trigger a plan regeneration on the backend; start
    // polling so the new plan is ready by the time the user opens it.
    if (data?.weekly_plan_id) {
      dietStore.planStatus = 'pending'
      dietStore.isGenerating = true
      dietStore.startPolling(data.weekly_plan_id)
    }
  } catch (err) {
    if (err.response?.status === 422) {
      const errors = err.response.data?.errors ?? {}
      Object.keys(fieldErrors).forEach((key) => {
        fieldErrors[key] = errors[key]?.[0] ?? ''
      })
    } else {
      saveError.value = 'Error al guardar. Por favor, inténtalo de nuevo.'
    }
  } finally {
    saving.value = false
  }
}

async function handleLogout() {
  try {
    await userStore.logout()
    router.push({ name: 'login' })
  } catch (err) {
    saveError.value = 'Error al cerrar sesión. Inténtalo de nuevo.'
  }
}
</script>

<style scoped>
/* ── Page layout ────────────────────────────────────────── */
.profile-page {
  max-width: 640px;
  margin: 0 auto;
  padding: var(--space-6) var(--space-5) var(--space-7);
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

/* ── Hero ───────────────────────────────────────────────── */
.profile-hero {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.hero-top {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}

.avatar {
  flex-shrink: 0;
  width: 56px;
  height: 56px;
  border-radius: var(--radius-full);
  background: var(--color-accent);
  color: var(--color-text);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--fs-lg);
  font-weight: 700;
  letter-spacing: 0.02em;
}

.hero-info {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.hero-name {
  font-size: var(--fs-xl);
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hero-goal {
  align-self: flex-start;
  font-size: var(--fs-xs);
  font-weight: 600;
  color: var(--color-accent-dark);
  background: rgba(255, 212, 0, 0.15);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-pill);
  text-transform: capitalize;
}

.hero-macros {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-2);
  padding-top: var(--space-4);
  border-top: 1px solid var(--border-subtle);
}

.hero-macro {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-1);
  text-align: center;
}

.hm-value {
  font-size: var(--fs-lg);
  font-weight: 700;
  color: var(--color-text);
  line-height: 1;
}

.hm-label {
  font-size: var(--fs-xs);
  font-weight: 600;
  color: var(--color-text-muted);
}

.c-protein { color: var(--color-protein); }
.c-carbs   { color: var(--color-carbs); }
.c-fat     { color: var(--color-fat); }

.hero-macros-hint {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0;
  padding-top: var(--space-4);
  border-top: 1px solid var(--border-subtle);
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
}

/* ── Tabs (segmented control) ───────────────────────────── */
.profile-tabs {
  display: flex;
  gap: var(--space-1);
  background: var(--color-bg);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-pill);
  padding: var(--space-1);
}

.profile-tab {
  flex: 1;
  padding: var(--space-2) var(--space-3);
  border: none;
  background: transparent;
  border-radius: var(--radius-pill);
  font-family: var(--font-sans);
  font-size: var(--fs-sm);
  font-weight: 600;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.profile-tab--active {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

/* ── Tab panels ─────────────────────────────────────────── */
.tab-panel {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  padding: var(--space-5);
}

/* Suplementos brings its own card */
.tab-panel--bare {
  background: none;
  box-shadow: none;
  padding: 0;
}

/* ── Save banners ───────────────────────────────────────── */
.save-banner {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  border-radius: var(--radius-md);
  padding: var(--space-3) var(--space-4);
  font-size: var(--fs-sm);
  font-weight: 500;
}

.save-banner--success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #15803d;
}

.save-banner--error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
}

/* ── Fields ─────────────────────────────────────────────── */
.field {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
}

.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}

@media (max-width: 400px) {
  .field-row { grid-template-columns: 1fr; }
}

.field-label {
  font-size: var(--fs-sm);
  font-weight: 600;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.field-label-optional {
  font-size: var(--fs-xs);
  font-weight: 400;
  color: var(--color-text-muted);
}

.field-input {
  width: 100%;
  padding: var(--space-3) var(--space-4);
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-bg);
  color: var(--color-text);
  font-family: var(--font-sans);
  font-size: var(--fs-base);
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  box-sizing: border-box;
  appearance: none;
}

.field-input::placeholder { color: var(--color-text-muted); }

.field-input:focus {
  border-color: var(--color-accent-dark);
  box-shadow: 0 0 0 3px rgba(255, 212, 0, 0.25);
}

.field-input--error { border-color: #f87171; }
.field-input--error:focus { box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.2); }

.field-error {
  font-size: var(--fs-sm);
  color: #dc2626;
  margin: 0;
}

.field-select {
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%238A8178' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.875rem center;
  padding-right: var(--space-6);
}

.field-unit-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.field-unit-wrap .field-input { padding-right: var(--space-7); }

.field-unit {
  position: absolute;
  right: 1rem;
  font-size: var(--fs-sm);
  font-weight: 600;
  color: var(--color-text-muted);
  pointer-events: none;
}

/* ── Radio goal cards ───────────────────────────────────── */
.radio-group {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-3);
}

@media (max-width: 400px) {
  .radio-group { grid-template-columns: 1fr; }
}

.radio-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-3) var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-bg);
  border: 1.5px solid var(--color-border);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
  text-align: center;
}

.radio-card:hover {
  border-color: var(--color-accent);
  background: rgba(255, 212, 0, 0.06);
}

.radio-card--active {
  border-color: var(--color-accent-dark);
  background: rgba(255, 212, 0, 0.12);
  box-shadow: 0 0 0 3px rgba(255, 212, 0, 0.2);
}

.radio-hidden {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.radio-icon { display: inline-flex; color: var(--color-text-muted); }
.radio-card--active .radio-icon { color: var(--color-accent-dark); }
.radio-label { font-size: var(--fs-sm); font-weight: 700; color: var(--color-text); }
.radio-desc  { font-size: var(--fs-xs);  font-weight: 500; color: var(--color-text-muted); }

/* ── Training days picker ───────────────────────────────── */
.days-picker {
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.day-btn {
  flex: 1;
  min-width: 36px;
  height: 40px;
  border-radius: var(--radius-md);
  border: 1.5px solid var(--color-border);
  background: var(--color-bg);
  color: var(--color-text-muted);
  font-family: var(--font-sans);
  font-size: var(--fs-sm);
  font-weight: 700;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, color 0.15s;
}

.day-btn:hover {
  border-color: var(--color-accent);
}

.day-btn--active {
  background: var(--color-accent);
  border-color: var(--color-accent);
  color: var(--color-text);
}

/* ── Buttons ────────────────────────────────────────────── */
.btn-primary {
  width: 100%;
  padding: var(--space-3) var(--space-5);
  background: var(--color-accent);
  color: var(--color-text);
  font-family: var(--font-sans);
  font-size: var(--fs-base);
  font-weight: 700;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  margin-top: var(--space-2);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  transition: background 0.15s ease, transform 0.1s ease;
}

.btn-primary:hover:not(:disabled) { background: var(--color-accent-dark); }
.btn-primary:active:not(:disabled) { transform: scale(0.98); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(31, 27, 22, 0.3);
  border-top-color: var(--color-text);
  border-radius: var(--radius-full);
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.actions-section {
  display: flex;
  justify-content: center;
}

.btn-logout {
  padding: var(--space-3) var(--space-5);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-sans);
  font-size: var(--fs-base);
  font-weight: 600;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-logout:hover {
  color: #dc2626;
  border-color: #fca5a5;
  background: #fef2f2;
}
</style>
