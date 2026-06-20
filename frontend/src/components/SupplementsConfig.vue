<!-- SupplementsConfig.vue
     Lets the user say which supplements they take. Protein-powder type foods
     (with macros) count towards macros and are injected into a meal; creatine
     and other macro-free supplements are reminder + shopping only. -->
<script setup>
import { ref, onMounted } from 'vue'
import { Check } from 'lucide-vue-next'
import api from '@/api/client'
import { useUserStore } from '@/stores/userStore'

const userStore = useUserStore()

const catalog = ref([])
const rows = ref([]) // { food, enabled, dosis_gramos, momento, afectaMacros }
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const error = ref('')

const momentoOptions = [
  { value: 'desayuno', label: 'Desayuno' },
  { value: 'pre_entreno', label: 'Pre-entreno' },
  { value: 'post_entreno', label: 'Post-entreno' },
  { value: 'cena', label: 'Cena' },
]

const affectsMacros = (food) =>
  (parseFloat(food.proteina) + parseFloat(food.carbos) + parseFloat(food.grasa)) > 0

onMounted(async () => {
  try {
    const [{ data: catalogData }, { data: current }] = await Promise.all([
      api.get('/supplements/catalog'),
      api.get('/supplements'),
    ])
    catalog.value = catalogData
    rows.value = catalogData.map((food) => {
      const existing = current.find((s) => s.food_id === food.id)
      return {
        food,
        afectaMacros: affectsMacros(food),
        enabled: !!existing,
        dosis_gramos: existing ? parseFloat(existing.dosis_gramos) : (affectsMacros(food) ? 30 : 5),
        momento: existing?.momento ?? (affectsMacros(food) ? 'post_entreno' : 'diario'),
      }
    })
  } catch (err) {
    error.value = 'No se pudieron cargar los suplementos.'
    console.error(err)
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  saved.value = false
  error.value = ''
  try {
    const supplements = rows.value
      .filter((r) => r.enabled)
      .map((r) => ({
        food_id: r.food.id,
        dosis_gramos: r.dosis_gramos,
        momento: r.afectaMacros ? r.momento : 'diario',
      }))
    await api.put('/supplements', { supplements })
    await userStore.fetchProfile()
    saved.value = true
    setTimeout(() => (saved.value = false), 3500)
  } catch (err) {
    error.value = 'Error al guardar los suplementos.'
    console.error(err)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="supp-section">
    <h2 class="section-title">Suplementos</h2>
    <p class="supp-help">
      Indica lo que tomas. La proteína en polvo cuenta en tus macros (se añade a una comida);
      la creatina y similares solo se recuerdan y se añaden a la compra.
    </p>

    <div v-if="saved" class="save-banner save-banner--success" role="status">
      <Check :size="16" :stroke-width="2.5" aria-hidden="true" /> Suplementos guardados. Tu plan se está regenerando.
    </div>
    <div v-if="error" class="save-banner save-banner--error" role="alert">{{ error }}</div>

    <p v-if="loading" class="supp-loading">Cargando…</p>

    <ul v-else class="supp-list">
      <li v-for="row in rows" :key="row.food.id" class="supp-row" :class="{ 'supp-row--on': row.enabled }">
        <label class="supp-toggle">
          <input type="checkbox" v-model="row.enabled" />
          <span class="supp-name">
            {{ row.food.nombre }}
            <span class="supp-tag" :class="row.afectaMacros ? 'supp-tag--macros' : 'supp-tag--info'">
              {{ row.afectaMacros ? 'Cuenta en macros' : 'Recordatorio + compra' }}
            </span>
          </span>
        </label>

        <div v-if="row.enabled" class="supp-controls">
          <div class="supp-dosis">
            <input type="number" min="0" step="1" v-model.number="row.dosis_gramos" class="supp-input" />
            <span class="supp-unit">g</span>
          </div>
          <select v-if="row.afectaMacros" v-model="row.momento" class="supp-input supp-select">
            <option v-for="opt in momentoOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
      </li>
    </ul>

    <button type="button" class="btn-primary" :disabled="saving || loading" @click="save">
      <span v-if="saving" class="btn-spinner" aria-hidden="true"></span>
      {{ saving ? 'Guardando…' : 'Guardar suplementos' }}
    </button>
  </section>
</template>

<style scoped>
.supp-section {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  box-shadow: var(--shadow-md);
}

.section-title {
  font-size: var(--fs-lg);
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 var(--space-2);
}

.supp-help {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
  margin: 0 0 var(--space-5);
  line-height: 1.5;
}

.supp-loading {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
}

.supp-list {
  list-style: none;
  padding: 0;
  margin: 0 0 var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.supp-row {
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-3) var(--space-4);
  background: var(--color-bg);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.supp-row--on {
  border-color: var(--color-accent-dark);
  background: rgba(255, 212, 0, 0.08);
}

.supp-toggle {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  cursor: pointer;
}

.supp-name {
  font-size: var(--fs-base);
  font-weight: 600;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.supp-tag {
  font-size: var(--fs-xs);
  font-weight: 600;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-pill);
}

.supp-tag--macros {
  background: rgba(22, 163, 74, 0.1);
  color: var(--color-protein);
}

.supp-tag--info {
  background: rgba(37, 99, 235, 0.1);
  color: var(--color-system);
}

.supp-controls {
  display: flex;
  gap: var(--space-3);
  align-items: center;
  padding-left: calc(16px + var(--space-3));
  flex-wrap: wrap;
}

.supp-dosis {
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.supp-input {
  padding: var(--space-2) var(--space-3);
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--fs-sm);
  outline: none;
  width: 90px;
}

.supp-input:focus {
  border-color: var(--color-accent-dark);
}

.supp-select {
  width: auto;
  cursor: pointer;
}

.supp-unit {
  font-size: var(--fs-sm);
  color: var(--color-text-muted);
}

.btn-primary {
  width: 100%;
  padding: var(--space-3) var(--space-5);
  background: var(--color-accent);
  color: var(--color-text);
  font-size: var(--fs-base);
  font-weight: 700;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  transition: background 0.15s ease;
}

.btn-primary:hover:not(:disabled) {
  background: var(--color-accent-dark);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.save-banner {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  border-radius: var(--radius-md);
  padding: var(--space-3) var(--space-4);
  font-size: var(--fs-sm);
  margin-bottom: var(--space-4);
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

.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(31, 27, 22, 0.3);
  border-top-color: var(--color-text);
  border-radius: var(--radius-full);
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
