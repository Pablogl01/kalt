<script setup>
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { motion, AnimatePresence, MotionConfig } from 'motion-v'
import { useNavStore } from '@/stores/navStore'
import { page } from '@/lib/motion'
import SidebarNav from '@/components/layout/SidebarNav.vue'
import BottomTabBar from '@/components/layout/BottomTabBar.vue'
import MobileHeader from '@/components/layout/MobileHeader.vue'

const route    = useRoute()
const navStore = useNavStore()

// Navigation chrome is only shown on authenticated (app) routes.
// Fullscreen flows (e.g. the onboarding wizard) opt out even though they require auth.
const showNav = computed(() => route.meta.requiresAuth === true && route.meta.fullscreen !== true)

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
  <!--
    reduced-motion="user" makes every motion-v component honour the OS
    "reduce motion" setting: transform/layout/spring animations are disabled
    and only opacity is animated. Renderless — adds no DOM wrapper.
    (CSS animations are handled separately in main.css; AnimatedNumber
    snaps to its target on its own.)
  -->
  <MotionConfig reduced-motion="user">
  <div class="kalt-layout" :class="{ 'kalt-layout--auth': !showNav }">

    <!-- Desktop sidebar — visible ≥1024px, auth routes only -->
    <SidebarNav v-if="showNav" class="kalt-sidebar" />

    <!-- Mobile top header — visible <1024px, auth routes only (Profile access) -->
    <MobileHeader v-if="showNav" class="kalt-mobile-header" />

    <!-- Main content area -->
    <main class="kalt-main">
      <RouterView v-slot="{ Component, route }">
        <AnimatePresence mode="wait">
          <motion.div
            :key="route.name"
            class="route-view"
            :variants="page"
            initial="hidden"
            animate="show"
            exit="exit"
          >
            <component :is="Component" />
          </motion.div>
        </AnimatePresence>
      </RouterView>
    </main>

    <!-- Mobile bottom tab bar — visible <1024px, auth routes only -->
    <BottomTabBar v-if="showNav" class="kalt-bottom-bar" />

  </div>
  </MotionConfig>
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
}

.kalt-main {
  flex: 1;
  overflow-y: auto;
  /* Reserve space for the mobile top header and bottom bar (only when nav is shown) */
  padding-top: v-bind("showNav ? 'var(--top-bar-height)' : '0'");
  /* Floating bottom bar: reserve its height + the gap below it + safe area */
  padding-bottom: v-bind("showNav ? 'calc(var(--bottom-bar-height) + var(--space-6) + env(safe-area-inset-bottom))' : '0'");
}

/* Mobile top header: mobile only */
.kalt-mobile-header {
  display: flex;
}

/* Bottom bar: mobile only */
.kalt-bottom-bar {
  display: flex;
}

@media (min-width: 1024px) {
  .kalt-mobile-header {
    display: none;
  }

  .kalt-bottom-bar {
    display: none;
  }

  /* Sidebar covers nav on desktop; no fixed bars to reserve space for */
  .kalt-main {
    padding-top: 0;
    padding-bottom: 0;
  }
}
</style>
