# Explora Siam · nueva web

Viajes de autor a Tailandia en grupo reducido. Este repositorio arranca con la **vista previa de la home** para validar con el cliente y con la propuesta de rediseño completa.

## Qué hay

- `index.html` + `img/`: vista previa estática de la home (escritorio y móvil, a escala). Se despliega tal cual en Vercel, sin build.
- `docs/2026-09-01-rediseno-web-propuesta.md`: estrategia, concepto, estructura de la landing, dirección de arte, motion, conversión por WhatsApp, arquitectura (Astro + Keystatic + Vercel), mapa de contenidos editable y prompt de diseño.
- `design/`: artboards de la home (formato `.dc.html`) y su disposición en el lienzo.

## Desplegar la vista previa

En [vercel.com/new](https://vercel.com/new) importa este repositorio, framework **Other**, directorio raíz, sin comando de build. `vercel.json` marca la vista previa como `noindex`.

## Siguiente fase

Proyecto Astro 5 con Keystatic como panel de edición, según `docs/`. Las fotos marcadas como "de muestra" en la vista previa son provisionales y se sustituyen por material real del cliente.
