import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    // Le dice a Vite dónde están tus archivos fuente
    root: 'assets',

    build: {
        // Dónde depositar los archivos compilados
        outDir: resolve(__dirname, 'public/build'),
        emptyOutDir: true,

		assetsDir: '',

		manifest: true,

        rollupOptions: {
            input: {
                // Un punto de entrada por página o sección
                app:       resolve(__dirname, 'assets/js/app.js'),
  //              ambiente:  resolve(__dirname, 'assets/js/pages/ambiente.js'),
 //               reportes:  resolve(__dirname, 'assets/js/pages/reportes.js'),
            }
        }
    }
});
