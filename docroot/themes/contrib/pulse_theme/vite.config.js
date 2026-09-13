import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    hmr: {
      overlay: false,
    },
  },
  optimizeDeps: {
    entries: ['./components/**/*.{yml,twig}'],
  },
  assetsInclude: ['**/*.twig'],
});
