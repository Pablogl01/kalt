<script setup>
import { useNavStore } from '@/stores/navStore'
import { navIcons } from './navIcons'

const nav = useNavStore()
</script>

<template>
  <nav class="bottom-bar" aria-label="Navegación móvil">
    <button
      v-for="item in nav.navItems"
      :key="item.name"
      :id="`bottom-nav-${item.name}`"
      class="tab-item"
      :class="{ 'tab-item--active': nav.isActive(item.name) }"
      :aria-current="nav.isActive(item.name) ? 'page' : undefined"
      @click="nav.setRoute(item.name)"
    >
      <component :is="navIcons[item.icon]" class="tab-icon" :size="22" :stroke-width="2" aria-hidden="true" />
      <span class="tab-label">{{ item.label }}</span>
    </button>
  </nav>
</template>

<style scoped>
.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: var(--bottom-bar-height);
  background-color: var(--color-surface-dark);
  border-top: 1px solid var(--color-border-dark);
  display: flex;
  align-items: stretch;
  z-index: 100;
  /* Safe area for iPhone home indicator */
  padding-bottom: env(safe-area-inset-bottom);
}

.tab-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-sans);
  cursor: pointer;
  padding: 6px 4px;
  transition: color 0.15s ease;
  -webkit-tap-highlight-color: transparent;
}

.tab-item:hover {
  color: #fff;
}

/* Active state: accent green — NOT orange */
.tab-item--active {
  color: var(--color-accent);
}

.tab-icon {
  flex-shrink: 0;
}

.tab-label {
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  white-space: nowrap;
}
</style>
