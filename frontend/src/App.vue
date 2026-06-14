<script setup>
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useNavStore } from '@/stores/navStore'
import SidebarNav from '@/components/layout/SidebarNav.vue'
import BottomTabBar from '@/components/layout/BottomTabBar.vue'

const route    = useRoute()
const navStore = useNavStore()

// Navigation chrome is only shown on authenticated (app) routes
const showNav = computed(() => route.meta.requiresAuth === true)

watch(
  () => route.name,
  (newName) => {
    if (newName) {
      navStore.currentRoute = newName
    }
  },
  { immediate: true }
)
</script>

<template>
  <div class="kalt-layout" :class="{ 'kalt-layout--auth': !showNav }">

    <!-- Desktop sidebar — visible ≥1024px, auth routes only -->
    <SidebarNav v-if="showNav" class="kalt-sidebar" />

    <!-- Main content area -->
    <main class="kalt-main">
      <RouterView />
    </main>

    <!-- Mobile bottom tab bar — visible <1024px, auth routes only -->
    <BottomTabBar v-if="showNav" class="kalt-bottom-bar" />

  </div>
</template>

<style scoped>
.kalt-layout {
  display: flex;
  min-height: 100dvh;
  background-color: var(--color-bg);
}

/* Auth layout: full-screen, no chrome */
.kalt-layout--auth {
  align-items: stretch;
}

/* Sidebar: desktop only */
.kalt-sidebar {
  display: none;
}

@media (min-width: 1024px) {
  .kalt-sidebar {
    display: flex;
    flex-direction: column;
    width: var(--sidebar-width);
    flex-shrink: 0;
  }

  .kalt-main {
    padding-bottom: 0;
  }
}

.kalt-main {
  flex: 1;
  overflow-y: auto;
  /* Reserve space for bottom bar on mobile (only when nav is shown) */
  padding-bottom: v-bind("showNav ? 'var(--bottom-bar-height)' : '0'");
}

/* Bottom bar: mobile only */
.kalt-bottom-bar {
  display: flex;
}

@media (min-width: 1024px) {
  .kalt-bottom-bar {
    display: none;
  }
}
</style>
