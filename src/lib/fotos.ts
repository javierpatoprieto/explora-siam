import textos from '../../content/fotos.json';

type Textos = { titulo: string; descripcion: string };
const mapa = textos as Record<string, Partial<Textos>>;

/**
 * Título y descripción de una foto, editables desde el panel (content/fotos.json).
 * Se buscan por el nombre del archivo original. Ojo: si dos fotos son idénticas,
 * Astro las publica como un solo archivo con el nombre de una de ellas, así que
 * la URL final no sirve; fsPath (no enumerable) sí guarda la ruta de origen.
 */
export function textosFoto(img: { src: string; fsPath?: string } | string | undefined): Textos {
  const ruta = typeof img === 'string' ? img : img?.fsPath || img?.src || '';
  const archivo = decodeURIComponent(ruta.split('?')[0].split(/[\\/]/).pop() ?? '');
  for (const [nombre, t] of Object.entries(mapa)) {
    const base = nombre.replace(/\.[^.]+$/, '');
    if (archivo === nombre || archivo.startsWith(base + '.')) {
      return { titulo: t.titulo ?? '', descripcion: t.descripcion ?? '' };
    }
  }
  return { titulo: '', descripcion: '' };
}
