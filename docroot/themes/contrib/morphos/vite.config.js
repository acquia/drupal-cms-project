import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { glob } from 'glob';

export default defineConfig({
  plugins: [tailwindcss()],
  build: {
    outDir: 'dist',
    rollupOptions: {
      input: glob.sync([
        path.resolve(__dirname, './base/**/*.{css,js}'),
        path.resolve(__dirname, './components/**/*.{css,js}'),
      ]),
      output: {
        entryFileNames: `js/[name].js`,
        chunkFileNames: `js/[name].js`,
        assetFileNames: `css/[name].css`,
      },
    },
  },
  publicDir: 'assets',
});
