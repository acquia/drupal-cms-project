// eslint-disable-next-line import/no-unresolved
import { defineConfig } from 'vite';
// eslint-disable-next-line import/no-unresolved
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [tailwindcss()],
  build: {
    outDir: 'build',
    rollupOptions: {
      input: {
        main: 'src/main.css',
        classic: 'src/classic.css',
        bright: 'src/bright.css',
      },
      output: {
        assetFileNames: '[name][extname]',
      },
    },
  },
});
