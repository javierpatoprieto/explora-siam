import type { APIRoute } from 'astro';
import { getEntry } from 'astro:content';

export const GET: APIRoute = async ({ site }) => {
  const datos = (await getEntry('site', 'site'))!.data;
  const base = site?.toString().replace(/\/$/, '') ?? 'https://explorasiam.com';
  const cuerpo = datos.indexar
    ? `User-agent: *\nAllow: /\nDisallow: /keystatic\nDisallow: /preview/\n\nSitemap: ${base}/sitemap-index.xml\n`
    : `User-agent: *\nDisallow: /\n`;
  return new Response(cuerpo, { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
};
