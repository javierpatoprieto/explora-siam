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

## Cómo se publica

La web se sirve **estática desde el hosting del cliente (Raiola)** y el **panel de edición vive en Vercel**, porque necesita Node y un hosting clásico no lo tiene.

```
Cliente edita en el panel (Vercel /keystatic)
        └─► commit automático en main
                └─► GitHub Action: npm run build:static
                        └─► sube dist/ por FTP a Raiola  →  explorasiam.com
```

Cada guardado del cliente actualiza la web en uno o dos minutos, sin tocar nada.

### 1. Panel de edición (Vercel, una sola vez)

1. En local, `npm run dev` y abre `/keystatic`. Sigue el asistente **Set up GitHub App**: crea la app en la cuenta de GitHub dueña del repositorio y copia las tres claves.
2. En el proyecto de Vercel, añade las variables `KEYSTATIC_GITHUB_CLIENT_ID`, `KEYSTATIC_GITHUB_CLIENT_SECRET` y `KEYSTATIC_SECRET` (ver `.env.example`).
3. Redespliega. El panel queda en `<proyecto>.vercel.app/keystatic`; se le puede poner un subdominio propio, por ejemplo `panel.explorasiam.com`.

Sin esas variables la web se publica igual: solo desaparece la ruta `/keystatic`.

### 2. Hosting del cliente (Raiola)

`npm run build:static` genera HTML plano en `dist/` (sin adaptador, con URLs tipo `preguntas.html` que el `.htaccess` sirve como `/preguntas`). Se puede subir a mano por FTP o dejar que lo haga la acción `.github/workflows/deploy-raiola.yml` en cada cambio.

En **Settings → Secrets and variables → Actions** del repositorio:

| Tipo | Nombre | Valor |
|---|---|---|
| Secret | `FTP_HOST` | servidor FTP de Raiola (por ejemplo `ftp.explorasiam.com`) |
| Secret | `FTP_USER` | usuario FTP |
| Secret | `FTP_PASSWORD` | contraseña |
| Variable | `FTP_DIR` | carpeta pública, normalmente `/public_html/` |
| Variable | `FTP_PROTOCOL` | `ftps` (o `sftp` si Raiola lo ofrece) |
| Variable | `SITE_URL` | `https://explorasiam.com` |

`public/.htaccess` ya lleva HTTPS forzado, dominio sin `www`, URLs limpias, página 404, compresión, caché de un año para imágenes y CSS, y las redirecciones desde las URLs de la web antigua en WordPress.

Para subirlo a mano: `npm run build:static` y copiar **todo el contenido** de `dist/` (incluido el `.htaccess`, que está oculto) a `public_html`.

### 3. Antes de dar el dominio por bueno

- Poner `"indexar": true` en `content/site.json` para permitir a Google.
- Comprobar que el certificado SSL de Raiola cubre el dominio con y sin `www`.

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
