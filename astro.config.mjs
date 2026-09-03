import { defineConfig } from 'astro/config';
import vercel from '@astrojs/vercel';
import sitemap from '@astrojs/sitemap';
import react from '@astrojs/react';
import keystatic from '@keystatic/astro';

// El panel de edición (/keystatic) se activa en desarrollo y, en producción,
// solo cuando existen las credenciales de la app de GitHub de Keystatic.
const withKeystatic =
  process.env.NODE_ENV !== 'production' || Boolean(process.env.KEYSTATIC_GITHUB_CLIENT_ID);

export default defineConfig({
  site: 'https://explorasiam.com',
  output: 'static',
  adapter: vercel(),
  trailingSlash: 'never',
  integrations: [sitemap({ filter: (p) => !p.includes('/keystatic') }), ...(withKeystatic ? [react(), keystatic()] : [])],
});
