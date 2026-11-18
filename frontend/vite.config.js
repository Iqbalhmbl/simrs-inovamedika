import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// SIMRS frontend. Talks to the Yii2 REST API (default http://localhost:8080/api)
// for all business writes, and to the Node.js service (default
// http://localhost:4000/api) for dashboard aggregation - see src/api/client.js.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
  },
});
