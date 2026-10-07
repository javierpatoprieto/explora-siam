type Site = { whatsapp: string; plantillas: Record<string, string> };

/** Construye un enlace wa.me con el mensaje predefinido del origen indicado. */
export function waUrl(site: Site, origen: string, vars: Record<string, string> = {}): string {
  const plantilla = site.plantillas[origen] ?? site.plantillas.flotante ?? '';
  const texto = plantilla.replace(/\{(\w+)\}/g, (m, k) => vars[k] ?? m);
  return `https://wa.me/${site.whatsapp}?text=${encodeURIComponent(texto)}`;
}

export const WA_ICON =
  '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20l1.3-3.9A8 8 0 1 1 8.1 19L4 20z"></path><path d="M9.5 9.5c.2 2.2 2.8 4.8 5 5l1.4-1.3-1.9-1-1 .7c-.9-.4-1.6-1.1-2-2l.7-1-1-1.9L9.5 9.5z"></path></svg>';

export function precio(n: number): string {
  return new Intl.NumberFormat('es-ES').format(n) + ' €';
}
