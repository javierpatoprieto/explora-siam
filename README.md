# Explora Siam · nueva web

Viajes de autor a Tailandia en grupo reducido. Web en **Astro** con contenido editable desde **Keystatic** y despliegue en **Vercel**.

## Estructura

- `src/` componentes, páginas y estilos de la web.
- `content/` todo lo que edita el cliente: `site.json` (datos de contacto, SEO, mensajes de WhatsApp), `home.json` (todos los bloques de la home), `salidas/`, `testimonios/`, `faqs/` y `legal/`.
- `src/assets/img/` imágenes (Astro las optimiza en el build: AVIF/WebP y varios tamaños).
- `public/video/` vídeos del hero y de la banda.
- `public/preview/` la vista previa estática anterior (se puede borrar cuando la web esté validada).
- `docs/` propuesta de rediseño completa. `design/` artboards de diseño.

## Desarrollo

```bash
npm install
npm run dev        # http://localhost:4321  ·  panel de edición en http://localhost:4321/keystatic
npm run build      # genera dist/
```

En local, el panel de edición escribe directamente en los archivos de `content/` e `src/assets/img/`. Cada cambio se sube con un commit normal.

## Panel de edición en producción (opcional)

Para que el cliente edite desde `explorasiam.com/keystatic` sin usar Git:

1. En local, abre `/keystatic` y sigue el asistente "Set up GitHub App": crea la app en la cuenta de GitHub y obtén las tres claves.
2. Añade en Vercel las variables `KEYSTATIC_GITHUB_CLIENT_ID`, `KEYSTATIC_GITHUB_CLIENT_SECRET` y `KEYSTATIC_SECRET` (ver `.env.example`).
3. Redespliega. Cada guardado desde el panel es un commit en `main` y Vercel publica la web en uno o dos minutos.

Sin esas variables, la web se publica igual (solo desaparece la ruta `/keystatic`).

## Otras variables

- `PUBLIC_GA_ID`: ID de Google Analytics 4. Si está vacío no se carga ningún script. Los clics a WhatsApp se envían como evento `whatsapp_click` con el origen (hero, salida, fundador…).

## Indexación

`content/site.json` → `"indexar": false` mantiene la web fuera de Google mientras se valida. Cambiar a `true` en el lanzamiento.

## Dossier en PDF

`dossier/index.html` es el dossier comercial (A4, 9 páginas) con la misma estética de la web. Para regenerarlo tras editarlo:

```bash
node dossier/render.mjs   # escribe public/dossier-mae-hong-son-loop.pdf
```

Necesita Chromium; por defecto usa `/opt/pw-browsers/chromium`, y se puede indicar otro con la variable `CHROMIUM_PATH`. El PDF queda enlazado desde el hero y desde la ficha del viaje (campo "Dossier en PDF" en el panel de edición).
