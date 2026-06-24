<script setup>
import { motion } from 'motion-v'
import { useNavStore } from '@/stores/navStore'
import { spring, tapSubtle } from '@/lib/motion'
import { navIcons } from './navIcons'

const nav = useNavStore()
</script>

<template>
  <nav class="bottom-bar" aria-label="Navegación móvil">
    <motion.button
      v-for="item in nav.navItems"
      :key="item.name"
      :id="`bottom-nav-${item.name}`"
      class="tab-item"
      :class="{ 'tab-item--active': nav.isActive(item.name) }"
      :aria-current="nav.isActive(item.name) ? 'page' : undefined"
      :while-press="tapSubtle"
      @click="nav.setRoute(item.name)"
    >
      <motion.span
        v-if="nav.isActive(item.name)"
        class="tab-indicator"
        layout-id="tab-indicator"
        :transition="spring.snappy"
        aria-hidden="true"
      />
      <motion.span
        class="tab-icon-wrap"
        :animate="{ scale: nav.isActive(item.name) ? 1.1 : 1, y: nav.isActive(item.name) ? -1 : 0 }"
        :transition="spring.snappy"
      >
        <component :is="navIcons[item.icon]" class="tab-icon" :size="22" :stroke-width="2" aria-hidden="true" />
      </motion.span>
      <span class="tab-label">{{ item.label }}</span>
    </motion.button>
  </nav>
</template>

<style scoped>
.bottom-bar {
  position: fixed;
  /* Floating: detached from the edges, with safe-area room below */
  bottom: calc(env(safe-area-inset-bottom) + var(--space-4));
  left: var(--space-2);
  right: var(--space-2);
  max-width: 480px;
  margin-inline: auto;
  height: var(--bottom-bar-height);
  background-color: var(--color-surface-dark);
  border: 1px solid var(--color-border-dark);
  border-radius: var(--radius-pill);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28), 0 2px 8px rgba(0, 0, 0, 0.18);
  display: flex;
  align-items: stretch;
  gap: var(--space-2);
  /* Fully rounded ends: inset the tabs past the curve so labels stay clear */
  padding-inline: var(--space-3);
  z-index: 100;
}

.tab-item {
  position: relative;
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

/* Sliding active indicator — animates between tabs via layoutId */
.tab-indicator {
  position: absolute;
  top: 0;
  width: 28px;
  height: 3px;
  border-radius: var(--radius-pill);
  background: var(--color-accent);
}

.tab-icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Hover only lightens inactive tabs; the active tab stays accent-colored
   (avoids sticky-hover turning the active icon white after tapping). */
.tab-item:not(.tab-item--active):hover {
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
  font-size: 0.625rem; /* 10px — fits the 5 labels even with the inter-tab gap */
  font-weight: 600;
  letter-spacing: normal;
  white-space: nowrap;
}
</style>
