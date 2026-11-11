import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // درخواست‌هایی که با /api شروع می‌شوند به بک اند (Laravel) فرستاده می‌شوند
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
})
