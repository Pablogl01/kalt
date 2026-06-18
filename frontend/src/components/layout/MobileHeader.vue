<script setup>
import { useNavStore } from '@/stores/navStore'
import { User } from 'lucide-vue-next'

const nav = useNavStore()
</script>

<template>
  <header class="mobile-header" aria-label="Cabecera de la aplicación">
    <span class="mh-logo">KALT</span>

    <button
      id="mobile-nav-profile"
      class="mh-profile"
      :class="{ 'mh-profile--active': nav.isActive('profile') }"
      :aria-current="nav.isActive('profile') ? 'page' : undefined"
      aria-label="Perfil"
      @click="nav.setRoute('profile')"
    >
      <User :size="22" :stroke-width="2" aria-hidden="true" />
    </button>
  </header>
</template>

<style scoped>
.mobile-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: var(--top-bar-height);
  background-color: var(--color-surface-dark);
  border-bottom: 1px solid var(--color-border-dark);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  z-index: 100;
}

.mh-logo {
  font-size: var(--fs-lg);
  font-weight: 700;
  color: var(--color-accent);
  letter-spacing: -0.02em;
  line-height: 1;
}

.mh-profile {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
  -webkit-tap-highlight-color: transparent;
}

.mh-profile:hover {
  background-color: rgba(255, 255, 255, 0.06);
  color: #fff;
}

/* Active state: accent green — NOT orange */
.mh-profile--active {
  background-color: rgba(168, 224, 99, 0.12);
  color: var(--color-accent);
}
</style>
