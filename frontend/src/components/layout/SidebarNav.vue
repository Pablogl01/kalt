<script setup>
import { computed } from 'vue'
import { motion } from 'motion-v'
import { useNavStore } from '@/stores/navStore'
import { spring, tapSubtle } from '@/lib/motion'
import { navIcons } from './navIcons'

const nav = useNavStore()

// The bottom bar wants Dashboard centred; in the sidebar it goes first.
const sidebarItems = computed(() => {
  const dashboard = nav.navItems.find(i => i.name === 'dashboard')
  const rest = nav.navItems.filter(i => i.name !== 'dashboard')
  return dashboard ? [dashboard, ...rest] : rest
})
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
      <li v-for="item in sidebarItems" :key="item.name">
        <motion.button
          :id="`nav-${item.name}`"
          class="nav-item"
          :class="{ 'nav-item--active': nav.isActive(item.name) }"
          :aria-current="nav.isActive(item.name) ? 'page' : undefined"
          :while-press="tapSubtle"
          @click="nav.setRoute(item.name)"
        >
          <!-- Sliding active pill (shared layoutId animates between items) -->
          <motion.span
            v-if="nav.isActive(item.name)"
            class="nav-active-pill"
            layout-id="sidebar-active-pill"
            :transition="spring.snappy"
            aria-hidden="true"
          />
          <motion.span
            class="nav-icon-wrap"
            :animate="{ scale: nav.isActive(item.name) ? 1.08 : 1 }"
            :transition="spring.snappy"
          >
            <component :is="navIcons[item.icon]" class="nav-icon" :size="20" :stroke-width="2" aria-hidden="true" />
          </motion.span>
          <span class="nav-label">{{ item.label }}</span>
        </motion.button>
      </li>
    </ul>

    <!-- Profile at bottom -->
    <div class="sidebar-profile">
      <motion.button
        id="nav-profile"
        class="nav-item"
        :class="{ 'nav-item--active': nav.isActive('profile') }"
        :aria-current="nav.isActive('profile') ? 'page' : undefined"
        :while-press="tapSubtle"
        @click="nav.setRoute('profile')"
      >
        <motion.span
          v-if="nav.isActive('profile')"
          class="nav-active-pill"
          layout-id="sidebar-active-pill"
          :transition="spring.snappy"
          aria-hidden="true"
        />
        <motion.span
          class="nav-icon-wrap"
          :animate="{ scale: nav.isActive('profile') ? 1.08 : 1 }"
          :transition="spring.snappy"
        >
          <component :is="navIcons[nav.profileItem.icon]" class="nav-icon" :size="20" :stroke-width="2" aria-hidden="true" />
        </motion.span>
        <span class="nav-label">{{ nav.profileItem.label }}</span>
      </motion.button>
    </div>
  </nav>
</template>

<style scoped>
.sidebar {
  --notch: 22px;
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
  background-color: var(--color-surface-dark);
  color: #fff;
  padding: 0;
  border-right: 1px solid var(--color-border-dark);
  overflow: visible;
  z-index: 10;
}

/* Inverted (concave) corners on the right edge: the sidebar colour extends
   past the top-right and bottom-right corners with a quarter-circle cut, so
   the content seems to curve into it. */
.sidebar::before,
.sidebar::after {
  content: "";
  position: absolute;
  left: 100%;
  width: var(--notch);
  height: var(--notch);
  background-color: var(--color-surface-dark);
  pointer-events: none;
}

.sidebar::before {
  top: 0;
  -webkit-mask: radial-gradient(circle at 100% 100%, transparent var(--notch), #000 calc(var(--notch) + 0.5px));
          mask: radial-gradient(circle at 100% 100%, transparent var(--notch), #000 calc(var(--notch) + 0.5px));
}

.sidebar::after {
  bottom: 0;
  -webkit-mask: radial-gradient(circle at 100% 0%, transparent var(--notch), #000 calc(var(--notch) + 0.5px));
          mask: radial-gradient(circle at 100% 0%, transparent var(--notch), #000 calc(var(--notch) + 0.5px));
}

.sidebar-logo {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 28px 20px 24px;
  border-bottom: 1px solid var(--color-border-dark);
}

.logo-text {
  font-size: var(--fs-xl);
  font-weight: 700;
  color: var(--color-accent);
  letter-spacing: -0.02em;
  line-height: 1;
}

.logo-tag {
  font-size: var(--fs-xs);
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
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 12px;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-sans);
  font-size: var(--fs-sm);
  font-weight: 500;
  cursor: pointer;
  text-align: left;
  transition: color 0.15s ease;
}

.nav-item:hover {
  color: #fff;
}

/* Hover wash sits below content, above the active pill's resting state. */
.nav-item:hover::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: var(--radius-sm);
  background-color: rgba(255, 255, 255, 0.06);
  z-index: 0;
}

/* Active state: accent green — NOT orange */
.nav-item--active,
.nav-item--active:hover {
  color: var(--color-accent);
}

.nav-item--active:hover::after {
  background-color: transparent;
}

/* Animated background that slides between items via shared layoutId */
.nav-active-pill {
  position: absolute;
  inset: 0;
  border-radius: var(--radius-sm);
  background-color: rgba(255, 212, 0, 0.14);
  z-index: 0;
}

.nav-icon-wrap {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.nav-icon {
  flex-shrink: 0;
}

.nav-label {
  position: relative;
  z-index: 1;
  font-weight: 500;
}
</style>
