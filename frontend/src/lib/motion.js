/* ============================================================
   KALT Motion presets — shared spring/transition configs for motion-v.
   The CSS side (durations, easings) lives in assets/css/main.css; this
   file is the JS counterpart for spring physics, which CSS can't express.

   Goal: an iOS-like feel — quick to respond, soft to settle, with a
   touch of overshoot on the playful interactions.

   Usage:
     import { spring, listContainer, listItem, tap } from '@/lib/motion'
     <motion.div :transition="spring.snappy" />
   ============================================================ */

// ── Spring physics presets ─────────────────────────────────
export const spring = {
  // Default UI spring: responsive, minimal overshoot. Toggles, taps.
  snappy: { type: 'spring', stiffness: 420, damping: 32, mass: 0.9 },
  // Softer settle for larger surfaces (cards, sheets entering).
  gentle: { type: 'spring', stiffness: 260, damping: 30, mass: 1 },
  // Playful overshoot for celebratory feedback (meal completed).
  bouncy: { type: 'spring', stiffness: 500, damping: 18, mass: 0.8 },
  // For drag release / sheet dismissal — fast, no bounce.
  stiff: { type: 'spring', stiffness: 600, damping: 40 },
}

// ── Tween fallbacks (mirror the CSS easing tokens) ──────────
export const tween = {
  fast: { duration: 0.15, ease: [0.32, 0.72, 0, 1] },
  base: { duration: 0.25, ease: [0.32, 0.72, 0, 1] },
  out: { duration: 0.4, ease: [0.22, 1, 0.36, 1] },
}

// ── Staggered list entrance ─────────────────────────────────
// Parent orchestrates children; each child fades + lifts into place.
export const listContainer = {
  hidden: {},
  show: {
    transition: { staggerChildren: 0.06, delayChildren: 0.04 },
  },
}

export const listItem = {
  hidden: { opacity: 0, y: 14 },
  show: { opacity: 1, y: 0, transition: spring.gentle },
}

// ── Tap / press micro-interaction ──────────────────────────
// Apply via :while-press to give buttons & cards physical feedback.
export const tap = { scale: 0.97 }
export const tapSubtle = { scale: 0.985 }

// ── Directional slide (date navigation, paged content) ─────
// Pass a direction (1 = forward/next, -1 = back) as custom state.
export const slide = {
  enter: (dir) => ({ opacity: 0, x: dir > 0 ? 32 : -32 }),
  center: { opacity: 1, x: 0, transition: spring.gentle },
  exit: (dir) => ({ opacity: 0, x: dir > 0 ? -32 : 32, transition: tween.fast }),
}

// ── Bottom sheet (modals, inline forms rising from below) ──
export const sheet = {
  hidden: { opacity: 0, y: 24, scale: 0.98 },
  show: { opacity: 1, y: 0, scale: 1, transition: spring.gentle },
  exit: { opacity: 0, y: 16, scale: 0.98, transition: tween.fast },
}

// ── Modal: dimmed backdrop + popping card ──────────────────
export const backdrop = {
  hidden: { opacity: 0 },
  show: { opacity: 1, transition: tween.base },
  exit: { opacity: 0, transition: tween.fast },
}

export const modalCard = {
  hidden: { opacity: 0, scale: 0.94, y: 12 },
  show: { opacity: 1, scale: 1, y: 0, transition: spring.gentle },
  exit: { opacity: 0, scale: 0.96, y: 8, transition: tween.fast },
}

// ── Page / route transition (cross-fade + slight lift) ─────
export const page = {
  hidden: { opacity: 0, y: 8 },
  show: { opacity: 1, y: 0, transition: { duration: 0.28, ease: [0.22, 1, 0.36, 1] } },
  exit: { opacity: 0, y: -6, transition: { duration: 0.18, ease: [0.32, 0.72, 0, 1] } },
}
