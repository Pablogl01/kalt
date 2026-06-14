<template>
  <div class="profile-page">

    <!-- ── Macros block ─────────────────────────────────── -->
    <section class="macros-section">
      <h2 class="section-title">Tus macros diarios</h2>

      <!-- If macros are calculated: show them -->
      <div v-if="userStore.macros" class="macros-grid">
        <div class="macro-card macro-card--calories">
          <span class="macro-value">{{ Math.round(userStore.macros.calorias) }}</span>
          <span class="macro-unit">kcal</span>
          <span class="macro-label">Calorías</span>
        </div>
        <div class="macro-card macro-card--protein">
          <span class="macro-value">{{ Math.round(userStore.macros.proteina) }}</span>
          <span class="macro-unit">g</span>
          <span class="macro-label">Proteína</span>
        </div>
        <div class="macro-card macro-card--carbs">
          <span class="macro-value">{{ Math.round(userStore.macros.carbos) }}</span>
          <span class="macro-unit">g</span>
          <span class="macro-label">Carbohidratos</span>
        </div>
        <div class="macro-card macro-card--fat">
          <span class="macro-value">{{ Math.round(userStore.macros.grasa) }}</span>
          <span class="macro-unit">g</span>
          <span class="macro-label">Grasa</span>
        </div>
      </div>

      <!-- If profile is incomplete: CTA -->
      <div v-else class="macros-cta">
        <div class="macros-cta__icon" aria-hidden="true">📊</div>
        <p class="macros-cta__text">
          Completa los campos de tu perfil para ver tus macros diarios calculados.
        </p>
      </div>
    </section>

    <!-- ── Profile form ──────────────────────────────────── -->
    <section class="form-section">
      <h2 class="section-title">Datos personales</h2>

      <!-- Save feedback -->
      <div v-if="saveSuccess" class="save-banner save-banner--success" role="status" aria-live="polite">
        ✓ Perfil actualizado correctamente
      </div>
      <div v-if="saveError" class="save-banner save-banner--error" role="alert" aria-live="polite">
        {{ saveError }}
      </div>

      <form id="profile-form" @submit.prevent="handleSave" novalidate>

        <!-- Name -->
        <div class="field">
          <label class="field-label" for="profile-name">Nombre</label>
          <input
            id="profile-name"
            v-model="form.name"
            type="text"
            class="field-input"
            :class="{ 'field-input--error': fieldErrors.name }"
            autocomplete="name"
            placeholder="Tu nombre"
          />
          <p v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</p>
        </div>

        <!-- Email -->
        <div class="field">
          <label class="field-label" for="profile-email">Email</label>
          <input
            id="profile-email"
            v-model="form.email"
            type="email"
            class="field-input"
            :class="{ 'field-input--error': fieldErrors.email }"
            autocomplete="email"
            placeholder="tu@email.com"
          />
          <p v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</p>
        </div>

        <hr class="form-divider" />
        <p class="form-section-label">Datos físicos</p>

        <!-- Sex + Age (row) -->
        <div class="field-row">
          <div class="field">
            <label class="field-label" for="profile-sexo">Sexo</label>
            <select
              id="profile-sexo"
              v-model="form.sexo"
              class="field-input field-select"
              :class="{ 'field-input--error': fieldErrors.sexo }"
            >
              <option value="" disabled>Seleccionar…</option>
              <option value="hombre">Hombre</option>
              <option value="mujer">Mujer</option>
            </select>
            <p v-if="fieldErrors.sexo" class="field-error">{{ fieldErrors.sexo }}</p>
          </div>

          <div class="field">
            <label class="field-label" for="profile-edad">Edad</label>
            <input
              id="profile-edad"
              v-model.number="form.edad"
              type="number"
              min="10"
              max="120"
              class="field-input"
              :class="{ 'field-input--error': fieldErrors.edad }"
              placeholder="años"
            />
            <p v-if="fieldErrors.edad" class="field-error">{{ fieldErrors.edad }}</p>
          </div>
        </div>

        <!-- Weight + Height (row) -->
        <div class="field-row">
          <div class="field">
            <label class="field-label" for="profile-peso">Peso</label>
            <div class="field-unit-wrap">
              <input
                id="profile-peso"
                v-model.number="form.peso"
                type="number"
                min="20"
                max="500"
                step="0.1"
                class="field-input"
                :class="{ 'field-input--error': fieldErrors.peso }"
                placeholder="0"
              />
              <span class="field-unit">kg</span>
            </div>
            <p v-if="fieldErrors.peso" class="field-error">{{ fieldErrors.peso }}</p>
          </div>

          <div class="field">
            <label class="field-label" for="profile-altura">Altura</label>
            <div class="field-unit-wrap">
              <input
                id="profile-altura"
                v-model.number="form.altura"
                type="number"
                min="100"
                max="250"
                class="field-input"
                :class="{ 'field-input--error': fieldErrors.altura }"
                placeholder="0"
              />
              <span class="field-unit">cm</span>
            </div>
            <p v-if="fieldErrors.altura" class="field-error">{{ fieldErrors.altura }}</p>
          </div>
        </div>

        <!-- Body fat (optional) -->
        <div class="field">
          <label class="field-label" for="profile-grasa-corporal">
            Grasa corporal
            <span class="field-label-optional">(opcional)</span>
          </label>
          <div class="field-unit-wrap">
            <input
              id="profile-grasa-corporal"
              v-model.number="form.grasa_corporal"
              type="number"
              min="1"
              max="60"
              step="0.1"
              class="field-input"
              :class="{ 'field-input--error': fieldErrors.grasa_corporal }"
              placeholder="0"
            />
            <span class="field-unit">%</span>
          </div>
          <p v-if="fieldErrors.grasa_corporal" class="field-error">{{ fieldErrors.grasa_corporal }}</p>
        </div>

        <hr class="form-divider" />
        <p class="form-section-label">Objetivo y actividad</p>

        <!-- Goal -->
        <div class="field">
          <label class="field-label" for="profile-objetivo">Objetivo</label>
          <div class="radio-group" id="profile-objetivo" role="group" aria-label="Objetivo">
            <label
              v-for="opt in objetivoOptions"
              :key="opt.value"
              class="radio-card"
              :class="{ 'radio-card--active': form.objetivo === opt.value }"
            >
              <input
                type="radio"
                :value="opt.value"
                v-model="form.objetivo"
                class="radio-hidden"
                :id="`objetivo-${opt.value}`"
              />
              <span class="radio-icon" aria-hidden="true">{{ opt.icon }}</span>
              <span class="radio-label">{{ opt.label }}</span>
              <span class="radio-desc">{{ opt.desc }}</span>
            </label>
          </div>
          <p v-if="fieldErrors.objetivo" class="field-error">{{ fieldErrors.objetivo }}</p>
        </div>

        <!-- Activity level -->
        <div class="field">
          <label class="field-label" for="profile-actividad">Nivel de actividad</label>
          <select
            id="profile-actividad"
            v-model="form.nivel_actividad"
            class="field-input field-select"
            :class="{ 'field-input--error': fieldErrors.nivel_actividad }"
          >
            <option value="" disabled>Seleccionar…</option>
            <option value="sedentario">Sedentario — Sin ejercicio o muy poco</option>
            <option value="ligero">Ligero — 1-3 días/semana</option>
            <option value="moderado">Moderado — 3-5 días/semana</option>
            <option value="alto">Alto — 6-7 días/semana o trabajo físico</option>
          </select>
          <p v-if="fieldErrors.nivel_actividad" class="field-error">{{ fieldErrors.nivel_actividad }}</p>
        </div>

        <!-- Submit -->
        <button
          id="profile-save"
          type="submit"
          class="btn-primary"
          :disabled="saving"
        >
          <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
          {{ saving ? 'Guardando…' : 'Guardar cambios' }}
        </button>

      </form>
    </section>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useUserStore } from '@/stores/userStore'

const userStore = useUserStore()

const saving     = ref(false)
const saveSuccess = ref(false)
const saveError   = ref('')
const fieldErrors = reactive({
  name: '', email: '', sexo: '', edad: '', peso: '',
  altura: '', grasa_corporal: '', objetivo: '', nivel_actividad: '',
})

const form = reactive({
  name:            '',
  email:           '',
  sexo:            '',
  edad:            '',
  peso:            '',
  altura:          '',
  grasa_corporal:  '',
  objetivo:        '',
  nivel_actividad: '',
})

const objetivoOptions = [
  { value: 'volumen',       icon: '💪', label: 'Volumen',       desc: '+300 kcal' },
  { value: 'mantenimiento', icon: '⚖️',  label: 'Mantenimiento', desc: 'TDEE' },
  { value: 'definicion',    icon: '🔥', label: 'Definición',    desc: '−300 kcal' },
]

// Initialise form from store on mount
onMounted(() => {
  if (userStore.user) {
    Object.keys(form).forEach((key) => {
      form[key] = userStore.user[key] ?? ''
    })
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
    await userStore.updateProfile(payload)
    saveSuccess.value = true
    setTimeout(() => (saveSuccess.value = false), 3500)
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
</script>

<style scoped>
/* ── Page layout ────────────────────────────────────────── */
.profile-page {
  max-width: 680px;
  margin: 0 auto;
  padding: 2rem 1.25rem 4rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 1.25rem;
}

/* ── Macros section ─────────────────────────────────────── */
.macros-section {
  background: var(--color-surface);
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(31, 27, 22, 0.06);
}

.macros-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.75rem;
}

@media (max-width: 480px) {
  .macros-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.macro-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 1rem 0.5rem;
  border-radius: 12px;
  background: var(--color-bg);
  border-top: 3px solid transparent;
  gap: 0.125rem;
}

.macro-card--calories { border-top-color: var(--color-calories); }
.macro-card--protein  { border-top-color: var(--color-protein);  }
.macro-card--carbs    { border-top-color: var(--color-carbs);    }
.macro-card--fat      { border-top-color: var(--color-fat);      }

.macro-value {
  font-size: 1.875rem;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1;
}

.macro-value--calories { color: var(--color-calories); }

.macro-unit {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--color-text-muted);
  margin-top: 0.125rem;
}

.macro-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
  margin-top: 0.375rem;
  text-align: center;
}

/* ── CTA when no macros ─────────────────────────────────── */
.macros-cta {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 1.5rem 1rem;
  background: var(--color-bg);
  border-radius: 12px;
  border: 1.5px dashed var(--color-border);
  text-align: center;
}

.macros-cta__icon {
  font-size: 2rem;
}

.macros-cta__text {
  font-size: 0.9375rem;
  color: var(--color-text-muted);
  margin: 0;
  max-width: 320px;
  line-height: 1.5;
}

/* ── Form section ───────────────────────────────────────── */
.form-section {
  background: var(--color-surface);
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(31, 27, 22, 0.06);
}

.save-banner {
  border-radius: 10px;
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  margin-bottom: 1.25rem;
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

.form-divider {
  border: none;
  border-top: 1px solid var(--color-border);
  margin: 1.5rem 0 1rem;
}

.form-section-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin: 0 0 1rem;
}

/* ── Fields ─────────────────────────────────────────────── */
.field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  margin-bottom: 1.125rem;
}

.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

@media (max-width: 400px) {
  .field-row { grid-template-columns: 1fr; }
}

.field-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.field-label-optional {
  font-size: 0.75rem;
  font-weight: 400;
  color: var(--color-text-muted);
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
  appearance: none;
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

.field-select {
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%238A8178' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.875rem center;
  padding-right: 2.5rem;
}

/* Unit input wrapper */
.field-unit-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.field-unit-wrap .field-input {
  padding-right: 3rem;
}

.field-unit {
  position: absolute;
  right: 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text-muted);
  pointer-events: none;
}

/* ── Radio goal cards ───────────────────────────────────── */
.radio-group {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.625rem;
}

@media (max-width: 400px) {
  .radio-group { grid-template-columns: 1fr; }
}

.radio-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  padding: 0.875rem 0.5rem;
  border-radius: 12px;
  background: var(--color-bg);
  border: 1.5px solid var(--color-border);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
  text-align: center;
}

.radio-card:hover {
  border-color: var(--color-accent);
  background: rgba(168, 224, 99, 0.06);
}

.radio-card--active {
  border-color: var(--color-accent-dark);
  background: rgba(168, 224, 99, 0.12);
  box-shadow: 0 0 0 3px rgba(168, 224, 99, 0.2);
}

.radio-hidden {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.radio-icon  { font-size: 1.375rem; }
.radio-label { font-size: 0.875rem; font-weight: 700; color: var(--color-text); }
.radio-desc  { font-size: 0.75rem;  font-weight: 500; color: var(--color-text-muted); }

/* ── Submit button ──────────────────────────────────────── */
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
  margin-top: 0.75rem;
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
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
