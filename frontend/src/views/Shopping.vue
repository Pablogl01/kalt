<script setup>
import { ref, onMounted, computed } from 'vue'
import { Check, ArrowLeftRight, ShoppingCart } from 'lucide-vue-next'
import { useDietStore } from '@/stores/dietStore'
import api from '@/api/client'
import SubstituteSelector from '@/components/SubstituteSelector.vue'

const dietStore = useDietStore()

const shoppingList = ref(null)
const isLoading = ref(true)
const isGenerating = ref(false)
const errorMessage = ref('')

const showSubstituteSelector = ref(false)
const selectedShoppingItem = ref(null)

// Categories configuration
const categories = ['proteina', 'carbos', 'verduras', 'frutas', 'lacteos', 'grasas', 'otros']

const categoryLabels = {
  proteina: 'Proteínas',
  carbos: 'Carbohidratos',
  verduras: 'Verduras',
  frutas: 'Frutas',
  lacteos: 'Lácteos',
  grasas: 'Grasas',
  otros: 'Otros'
}

// Collapsed states per category
const collapsedCategories = ref({
  proteina: false,
  carbos: false,
  verduras: false,
  frutas: false,
  lacteos: false,
  grasas: false,
  otros: false
})

onMounted(async () => {
  await fetchShoppingList()
})

async function fetchShoppingList() {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get('/shopping-lists/active')
    shoppingList.value = data
  } catch (err) {
    if (err.response?.status === 404) {
      shoppingList.value = null
    } else {
      console.error('Error fetching shopping list:', err)
      errorMessage.value = 'No se pudo cargar la lista de la compra.'
    }
  } finally {
    isLoading.value = false
  }
}

async function generateList() {
  isGenerating.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.post('/shopping-lists/generate')
    shoppingList.value = data
  } catch (err) {
    console.error('Error generating shopping list:', err)
    errorMessage.value = 'Error al generar la lista de la compra.'
  } finally {
    isGenerating.value = false
  }
}

async function regenerateList() {
  if (window.confirm('Regenerar la lista borrará los ítems que hayas marcado como disponibles. ¿Continuar?')) {
    await generateList()
  }
}

async function markAsHave(item) {
  try {
    const { data } = await api.patch(`/shopping-items/${item.id}/have`)
    // Update local item
    const idx = shoppingList.value.shopping_items.findIndex(i => i.id === item.id)
    if (idx !== -1) {
      shoppingList.value.shopping_items[idx] = data
    }
  } catch (err) {
    console.error('Error marking item as owned:', err)
  }
}

function openSubstituteSelector(item) {
  // Mark as no_lo_quiero locally to reflect instantly,
  // although SubstituteSelector will also patch this on backend.
  item.no_lo_quiero = true
  selectedShoppingItem.value = item
  showSubstituteSelector.value = true
}

function handleSubstituteSelected(updatedItem) {
  const idx = shoppingList.value.shopping_items.findIndex(i => i.id === updatedItem.id)
  if (idx !== -1) {
    shoppingList.value.shopping_items[idx] = updatedItem
  }
}

// Group items by category
const groupedItems = computed(() => {
  if (!shoppingList.value || !shoppingList.value.shopping_items) return {}
  
  const groups = {}
  categories.forEach(cat => {
    groups[cat] = []
  })
  
  shoppingList.value.shopping_items.forEach(item => {
    const cat = item.categoria || 'otros'
    if (!groups[cat]) {
      groups[cat] = []
    }
    groups[cat].push(item)
  })
  
  // Sort items in each group: owned items at the end
  Object.keys(groups).forEach(cat => {
    groups[cat].sort((a, b) => {
      if (a.tengo_en_casa && !b.tengo_en_casa) return 1
      if (!a.tengo_en_casa && b.tengo_en_casa) return -1
      return a.food.nombre.localeCompare(b.food.nombre)
    })
  })
  
  return groups
})

// Count pending items (excluye tengo_en_casa=true)
const pendingCount = computed(() => {
  if (!shoppingList.value || !shoppingList.value.shopping_items) return 0
  return shoppingList.value.shopping_items.filter(item => !item.tengo_en_casa).length
})

function toggleCategory(cat) {
  collapsedCategories.value[cat] = !collapsedCategories.value[cat]
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('es-ES', {
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<template>
  <div class="shopping-container">
    <!-- Loading state -->
    <div v-if="isLoading" class="state-container">
      <div class="spinner"></div>
      <p class="state-text">Cargando tu lista de la compra...</p>
    </div>

    <!-- Error state -->
    <div v-else-if="errorMessage" class="state-container">
      <p class="error-text">{{ errorMessage }}</p>
      <button class="btn btn--primary" @click="fetchShoppingList">Reintentar</button>
    </div>

    <!-- Active List View -->
    <div v-else-if="shoppingList" class="shopping-content animate-fade-in">
      <header class="shopping-header">
        <div>
          <h1 class="page-title">Compra</h1>
          <p class="generation-date">Generada el {{ formatDate(shoppingList.generada_en) }}</p>
        </div>
        
        <div class="header-actions">
          <div class="pending-badge">
            <span class="pending-number">{{ pendingCount }}</span>
            <span class="pending-label">pendientes</span>
          </div>
          <button class="btn btn--secondary" @click="regenerateList" :disabled="isGenerating">
            {{ isGenerating ? 'Regenerando...' : 'Regenerar lista' }}
          </button>
        </div>
      </header>

      <!-- Category sections -->
      <div class="categories-list">
        <div 
          v-for="cat in categories" 
          :key="cat" 
          v-show="groupedItems[cat] && groupedItems[cat].length > 0"
          class="category-card"
        >
          <button class="category-header-btn" @click="toggleCategory(cat)">
            <span class="category-title">
              {{ categoryLabels[cat] }}
              <span class="category-count">({{ groupedItems[cat].length }})</span>
            </span>
            <span class="collapse-icon" :class="{ 'collapse-icon--collapsed': collapsedCategories[cat] }">▾</span>
          </button>

          <div v-show="!collapsedCategories[cat]" class="category-body animate-slide-down">
            <div 
              v-for="item in groupedItems[cat]" 
              :key="item.id" 
              class="shopping-item-row"
              :class="{ 'shopping-item-row--owned': item.tengo_en_casa }"
            >
              <div class="item-info">
                <span class="item-name">
                  {{ item.sustituido_por_food_id ? item.substitute_food?.nombre : item.food.nombre }}
                </span>
                <span class="item-amount">
                  {{ item.sustituido_por_food_id ? Math.round(item.cantidad_recalculada) : Math.round(item.cantidad_total) }}g
                </span>
                <span v-if="item.sustituido_por_food_id" class="substitute-badge">Sustituido</span>
              </div>

              <div class="item-actions">
                <!-- Check action -->
                <button 
                  v-if="!item.tengo_en_casa" 
                  class="action-btn action-btn--check"
                  @click="markAsHave(item)"
                  title="Ya lo tengo"
                  aria-label="Ya lo tengo"
                >
                  <Check :size="15" :stroke-width="2.5" aria-hidden="true" />
                </button>
                <span v-else class="owned-indicator">Disponible</span>

                <!-- Substitute action -->
                <button 
                  v-if="!item.tengo_en_casa" 
                  class="action-btn action-btn--substitute"
                  @click="openSubstituteSelector(item)"
                  title="No lo quiero"
                  aria-label="Sustituir este alimento"
                >
                  <ArrowLeftRight :size="15" :stroke-width="2" aria-hidden="true" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty/No list state -->
    <div v-else class="state-container animate-fade-in">
      <div class="empty-icon"><ShoppingCart :size="56" :stroke-width="1.75" aria-hidden="true" /></div>
      <h2 class="empty-title">Sin lista de la compra</h2>
      <p class="empty-desc">Genera tu plan semanal primero para poder ver o generar la lista de la compra automática.</p>
      
      <button 
        v-if="!dietStore.activePlan" 
        class="btn btn--primary" 
        @click="$router.push('/plan')"
      >
        Genera tu plan primero
      </button>
      <button 
        v-else 
        class="btn btn--primary" 
        @click="generateList" 
        :disabled="isGenerating"
      >
        {{ isGenerating ? 'Generando...' : 'Generar lista de la compra' }}
      </button>
    </div>

    <!-- Unified substitute selector modal -->
    <SubstituteSelector
      v-if="showSubstituteSelector && selectedShoppingItem"
      :mealItemId="selectedShoppingItem.id"
      :foodName="selectedShoppingItem.food?.nombre"
      context="shopping"
      @selected="handleSubstituteSelected"
      @close="showSubstituteSelector = false; selectedShoppingItem = null"
    />
  </div>
</template>

<style scoped>
.shopping-container {
  max-width: 680px;
  margin: 0 auto;
  padding: 1.5rem 1rem 4rem;
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - var(--bottom-bar-height, 64px));
}

@media (min-width: 1024px) {
  .shopping-container {
    padding: 2.5rem 2rem;
    min-height: 100vh;
  }
}

.shopping-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.shopping-header {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid rgba(138, 129, 120, 0.12);
}

@media (min-width: 640px) {
  .shopping-header {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-end;
  }
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-text, #1C1A17);
  margin: 0 0 0.25rem;
}

.generation-date {
  font-size: 0.875rem;
  color: var(--color-text-muted, #8A8178);
  margin: 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.pending-badge {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  background-color: var(--color-surface, #FFFFFF);
  border: 1px solid rgba(138, 129, 120, 0.15);
  border-radius: 9999px;
  padding: 0.25rem 0.75rem;
  font-size: 0.8125rem;
}

.pending-number {
  font-weight: 700;
  color: var(--color-text, #1C1A17);
}

.pending-label {
  color: var(--color-text-muted, #8A8178);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
  border-radius: 12px;
  padding: 0.625rem 1.25rem;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
}

.btn--primary {
  background-color: var(--color-accent, #A8E063);
  color: var(--color-text, #1C1A17);
}

.btn--primary:hover {
  background-color: var(--color-accent-dark);
  transform: translateY(-1px);
}

.btn--secondary {
  background-color: var(--color-surface, #FFFFFF);
  border: 1px solid rgba(138, 129, 120, 0.2);
  color: var(--color-text, #1C1A17);
}

.btn--secondary:hover {
  background-color: #FAF8F5;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.categories-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.category-card {
  background-color: var(--color-surface, #FFFFFF);
  border: 1px solid rgba(138, 129, 120, 0.15);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
}

.category-header-btn {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
}

.category-title {
  font-weight: 700;
  color: var(--color-text, #1C1A17);
  font-size: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.category-count {
  font-weight: 500;
  font-size: 0.8125rem;
  color: var(--color-text-muted, #8A8178);
}

.collapse-icon {
  font-size: 1.25rem;
  color: var(--color-text-muted, #8A8178);
  transition: transform 0.2s ease;
}

.collapse-icon--collapsed {
  transform: rotate(-90deg);
}

.category-body {
  border-top: 1px solid rgba(138, 129, 120, 0.08);
  padding: 0.5rem 1.25rem 1rem;
  display: flex;
  flex-direction: column;
}

.shopping-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid rgba(138, 129, 120, 0.06);
}

.shopping-item-row:last-child {
  border-bottom: none;
}

.item-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.item-name {
  font-weight: 500;
  color: var(--color-text, #1C1A17);
}

.item-amount {
  font-size: 0.8125rem;
  background-color: #FAF8F5;
  border: 1px solid rgba(138, 129, 120, 0.1);
  border-radius: 6px;
  padding: 0.125rem 0.375rem;
  color: var(--color-text-muted, #8A8178);
  font-weight: 600;
}

.substitute-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  background-color: rgba(37, 99, 235, 0.06);
  color: var(--color-system);
  border: 1px solid rgba(37, 99, 235, 0.12);
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
}

.shopping-item-row--owned .item-name {
  text-decoration: line-through;
  color: var(--color-text-muted, #8A8178);
}

.shopping-item-row--owned .item-amount {
  opacity: 0.5;
}

.item-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.action-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1px solid rgba(138, 129, 120, 0.2);
  background-color: var(--color-surface, #FFFFFF);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
  color: var(--color-text-muted, #8A8178);
}

.action-btn--check:hover {
  background-color: var(--color-accent, #A8E063);
  border-color: var(--color-accent, #A8E063);
  color: var(--color-text, #1C1A17);
}

.action-btn--substitute:hover {
  background-color: #FAF8F5;
  border-color: var(--color-text-muted, #8A8178);
  color: var(--color-text, #1C1A17);
}

.owned-indicator {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-accent-dark);
  background-color: rgba(168, 224, 99, 0.1);
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
}

/* State container layouts */
.state-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 3rem 1.5rem;
}

.empty-icon {
  display: flex;
  justify-content: center;
  color: var(--color-text-muted);
  margin-bottom: 1.5rem;
}

.empty-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-text, #1C1A17);
  margin: 0 0 0.5rem;
}

.empty-desc {
  color: var(--color-text-muted, #8A8178);
  max-width: 320px;
  margin: 0 0 1.5rem;
  font-size: 0.9375rem;
  line-height: 1.5;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(168, 224, 99, 0.1);
  border-top-color: var(--color-accent, #A8E063);
  border-radius: 50%;
  margin-bottom: 1.25rem;
  animation: spin 0.8s linear infinite;
}

.state-text {
  color: var(--color-text-muted, #8A8178);
  font-weight: 500;
}

.error-text {
  color: #DC2626;
  font-weight: 600;
  margin-bottom: 1rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.animate-fade-in {
  animation: fadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.animate-slide-down {
  animation: slideDown 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
