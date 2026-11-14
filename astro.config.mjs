import { defineConfig } from 'astro/config';
import vercel from '@astrojs/vercel';
import sitemap from '@astrojs/sitemap';
import react from '@astrojs/react';
import keystatic from '@keystatic/astro';

// El panel de edición (/keystatic) se activa en desarrollo y, en producción,
// solo cuando existen las credenciales de la app de GitHub de Keystatic.
const withKeystatic =
  process.env.NODE_ENV !== 'production' || Boolean(process.env.KEYSTATIC_GITHUB_CLIENT_ID);

// SITE_TARGET=static genera HTML plano para subir por FTP a un hosting clásico
// (Raiola): sin adaptador, sin panel y con URLs tipo /preguntas.html que el
// .htaccess sirve como /preguntas. Sin esa variable se compila para Vercel,
// que es donde vive el panel de edición.
const estatico = process.env.SITE_TARGET === 'static';

export default defineConfig({
  site: process.env.SITE_URL || 'https://explorasiam.com',
  output: 'static',
  ...(estatico ? { build: { format: 'file' } } : { adapter: vercel() }),
  trailingSlash: 'never',
  integrations: [
    sitemap({ filter: (p) => !p.includes('/keystatic') }),
    ...(!estatico && withKeystatic ? [react(), keystatic()] : []),
  ],
});
