/** Icono de Instagram, mismo trazo que el de WhatsApp. */
export const IG_ICON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor"/></svg>';

/** "@explora_siam" a partir de la URL del perfil. */
export function igUsuario(url: string): string {
  const u = (url || '').replace(/[?#].*$/, '').replace(/\/+$/, '').split('/').pop() || '';
  return u ? `@${u}` : 'Instagram';
}
