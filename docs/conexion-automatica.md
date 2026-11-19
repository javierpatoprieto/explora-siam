# Publicación automática

Cuando Dani guarda algo en `explorasiam.com/panel`, la web se actualiza sola.
No hay que tocar nada.

## Cómo funciona

```
Dani guarda en el panel
        ↓
el panel hace un commit en GitHub (javierpatoprieto/explora-siam)
        ↓
GitHub compila la web y la deja en la rama "publicado",
con un manifiesto que dice qué archivos hay y el hash de cada uno
        ↓
el panel compara ese manifiesto con lo que tiene el hosting
y se descarga solo lo que ha cambiado
        ↓
explorasiam.com actualizado
```

Lo importante del último paso: **es el hosting el que va a buscar la web**, no
GitHub el que la empuja. Por eso no hace falta guardar en ningún sitio la
contraseña del hosting. El repositorio es público, así que la descarga ni
siquiera necesita credenciales; si algún día lo pasas a privado, seguirá
funcionando con el token que ya tiene el panel en su `config.php`.

La primera publicación se descarga la web entera (unos 80 archivos, 9 MB, en
torno a un minuto). A partir de ahí solo baja lo que cambia: un cambio de texto
son uno o dos archivos y va en segundos.

## Lo que la publicación no toca nunca

- `public_html/video/` — los vídeos que sube Dani desde el panel
- `public_html/panel/` — el panel y su `config.php`

Están bloqueados en el código, no solo excluidos por configuración: cualquier
ruta que empiece por ahí se rechaza, igual que las rutas absolutas o con `..`.
Lo único que se borra automáticamente son los archivos viejos de `_astro/`, que
llevan un hash en el nombre y ya no los referencia nadie.

## Puesta en marcha

1. Sube la carpeta `panel/` a `public_html/panel/`.
2. Crea `panel/config.php` a partir de `config.example.php` con la contraseña de
   Dani y un token de GitHub (permiso *Contents: Read and write* sobre
   `explora-siam`).
3. Entra en `explorasiam.com/panel`. La franja de arriba detectará que la web
   no está al día y publicará sola.

## Si algo falla

La franja del panel dice qué ha pasado. Los casos posibles:

| Mensaje | Qué significa |
| --- | --- |
| «Todavía no hay ninguna versión preparada» | GitHub aún está compilando, o falló el flujo *Preparar la web para el hosting*. Míralo en la pestaña Actions. |
| «no se ha podido escribir (¿permisos?)» | El usuario de PHP no puede escribir en `public_html`. Se arregla desde el gestor de archivos de cPanel. |
| «Este hosting no tiene cURL activado» | Raro en Raiola. Se activa desde cPanel → *Seleccionar versión de PHP*. |

También puedes forzar una recompilación desde Actions → *Preparar la web para
el hosting* → **Run workflow**, y luego pulsar *Publicar ahora* en el panel.

## La vía por FTP (opcional)

Existe además una segunda vía, apagada, que sube la web por FTP desde GitHub.
No hace falta para nada: solo es útil si algún día quieres publicar sin pasar
por el panel.

Para encenderla, añade en Settings → Secrets and variables → Actions los
secretos `FTP_HOST`, `FTP_USER` y `FTP_PASSWORD` de una cuenta FTP limitada a
`public_html` (nunca la principal de cPanel; el usuario lleva el dominio:
`deploy@explorasiam.com`). Mientras no estén, esos trabajos simplemente se
saltan sin dar error.

Para comprobarlos antes de fiarte: Actions → *Probar conexión con Raiola* →
**Run workflow**. Entra, escribe un archivo de prueba y lo borra, sin tocar la
web, y te dice en castellano qué falla si falla.

Variables opcionales, en la pestaña *Variables*:

| Nombre | Por defecto | Cuándo cambiarlo |
| --- | --- | --- |
| `SITE_URL` | `https://explorasiam.com` | — |
| `PUBLIC_GA_ID` | vacío | el `G-…` de Analytics cuando lo tengas |
| `FTP_DIR` | `/public_html/` | solo para la vía por FTP |
| `FTP_PROTOCOL` | `ftps` | solo para la vía por FTP |
| `FTP_PORT` | `21` | solo para la vía por FTP |
