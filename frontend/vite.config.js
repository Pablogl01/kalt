import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'
import dns from 'node:dns'

dns.setDefaultResultOrder('ipv4first')

export default defineConfig({
  plugins: [
    tailwindcss(),
    vue(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    // Docker bind-mounts on Windows/macOS don't propagate inotify events to the
    // Linux container, so Vite's watcher never sees edits and serves stale
    // modules. Polling forces it to detect changes (enables HMR without a restart).
    watch: {
      usePolling: true,
      interval: 100,
    },
    proxy: {
      '/api': {
        target: 'http://nginx',
        changeOrigin: true,
        family: 4,
      },
      '/sanctum': {
        target: 'http://nginx',
        changeOrigin: true,
        family: 4,
      }
    }
  }
})
