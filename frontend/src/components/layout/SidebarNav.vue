<script setup>
import { useNavStore } from '@/stores/navStore'

const nav = useNavStore()
</script>

<template>
  <nav class="sidebar" aria-label="Navegación principal">
    <!-- Logo -->
    <div class="sidebar-logo">
      <span class="logo-text">KALT</span>
      <span class="logo-tag">Nutrición</span>
    </div>

    <!-- Nav items -->
    <ul class="sidebar-nav" role="list">
      <li v-for="item in nav.navItems" :key="item.name">
        <button
          :id="`nav-${item.name}`"
          class="nav-item"
          :class="{ 'nav-item--active': nav.isActive(item.name) }"
          :aria-current="nav.isActive(item.name) ? 'page' : undefined"
          @click="nav.setRoute(item.name)"
        >
          <span class="nav-icon" aria-hidden="true">{{ getIcon(item.icon) }}</span>
          <span class="nav-label">{{ item.label }}</span>
        </button>
      </li>
    </ul>

    <!-- Profile at bottom -->
    <div class="sidebar-profile">
      <button
        id="nav-profile"
        class="nav-item"
        :class="{ 'nav-item--active': nav.isActive('profile') }"
        @click="nav.setRoute('profile')"
      >
        <span class="nav-icon" aria-hidden="true">👤</span>
        <span class="nav-label">{{ nav.profileItem.label }}</span>
      </button>
    </div>
  </nav>
</template>

<script>
// Icon helper — Phase 1 uses emoji, Phase 2+ will use an icon library
function getIcon(name) {
  const icons = {
    'home': '🏠',
    'calendar': '📅',
    'check-circle': '✅',
    'shopping-cart': '🛒',
    'bar-chart': '📊',
    'user': '👤',
  }
  return icons[name] ?? '●'
}
</script>

<style scoped>
.sidebar {
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
  background-color: var(--color-surface-dark);
  color: #fff;
  padding: 0;
  border-right: 1px solid var(--color-border-dark);
  overflow: hidden;
}

.sidebar-logo {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 28px 20px 24px;
  border-bottom: 1px solid var(--color-border-dark);
}

.logo-text {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-accent);
  letter-spacing: -0.02em;
  line-height: 1;
}

.logo-tag {
  font-size: 0.65rem;
  font-weight: 500;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-top: 2px;
}

.sidebar-nav {
  list-style: none;
  margin: 0;
  padding: 12px 12px 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sidebar-profile {
  padding: 12px;
  border-top: 1px solid var(--color-border-dark);
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 12px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-sans);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  text-align: left;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.nav-item:hover {
  background-color: rgba(255, 255, 255, 0.06);
  color: #fff;
}

/* Active state: accent green — NOT orange */
.nav-item--active {
  background-color: rgba(168, 224, 99, 0.12);
  color: var(--color-accent);
}

.nav-item--active:hover {
  background-color: rgba(168, 224, 99, 0.16);
}

.nav-icon {
  width: 20px;
  text-align: center;
  font-size: 1rem;
  flex-shrink: 0;
}

.nav-label {
  font-weight: 500;
}
</style>
