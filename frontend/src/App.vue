<script setup>
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useNavStore } from '@/stores/navStore'
import SidebarNav from '@/components/layout/SidebarNav.vue'
import BottomTabBar from '@/components/layout/BottomTabBar.vue'

const route = useRoute()
const navStore = useNavStore()

onMounted(() => {
  if (route.name) {
    navStore.currentRoute = route.name
  }
})
</script>

<template>
  <div class="kalt-layout">
    <!-- Desktop sidebar — visible ≥1024px -->
    <SidebarNav class="kalt-sidebar" />

    <!-- Main content area -->
    <main class="kalt-main">
      <RouterView />
    </main>

    <!-- Mobile bottom tab bar — visible <1024px -->
    <BottomTabBar class="kalt-bottom-bar" />
  </div>
</template>

<style scoped>
.kalt-layout {
  display: flex;
  min-height: 100dvh;
  background-color: var(--color-bg);
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
  /* Reserve space for bottom bar on mobile */
  padding-bottom: var(--bottom-bar-height);
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
