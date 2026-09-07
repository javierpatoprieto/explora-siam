# Conectar el panel con la web (publicación automática)

Objetivo: que cuando Dani guarde algo en `explorasiam.com/panel`, la web se
actualice sola, sin que tengas que tocar nada.

## Cómo funciona

```
Dani guarda en el panel
        ↓
el panel hace un commit en GitHub (javierpatoprieto/explora-siam)
        ↓
GitHub Actions compila la web
        ↓
la sube por FTP a Raiola
        ↓
explorasiam.com actualizado (≈2 minutos)
```

Los tres primeros pasos ya funcionan. El cuarto necesita unas credenciales de
FTP guardadas en GitHub, y hasta que estén **los cambios del panel se quedan
guardados en GitHub pero no llegan a la web**.

## Paso 1 · Crear una cuenta FTP en cPanel

En cPanel → **Cuentas FTP** → *Añadir cuenta FTP*:

| Campo | Valor |
| --- | --- |
| Iniciar sesión | `deploy` |
| Dominio | `explorasiam.com` |
| Directorio | `public_html` |
| Cuota | Ilimitada |

Importante: **no uses la cuenta principal de cPanel**. Esta cuenta solo puede
tocar `public_html`, así que si se filtrara no daría acceso al hosting entero.

Al crearla, el usuario completo es `deploy@explorasiam.com` (con el dominio,
no solo `deploy`). Apunta la contraseña.

## Paso 2 · Guardar las credenciales en GitHub

En el repo → **Settings** → **Secrets and variables** → **Actions** →
pestaña *Secrets* → *New repository secret*, tres veces:

| Nombre | Valor |
| --- | --- |
| `FTP_HOST` | el servidor de Raiola, p. ej. `com1034.raiolanetworks.es` |
| `FTP_USER` | `deploy@explorasiam.com` |
| `FTP_PASSWORD` | la contraseña de esa cuenta |

Una vez guardados no se pueden volver a leer, ni siquiera por ti. Si los
pierdes, se cambia la contraseña en cPanel y se vuelve a pegar.

En la pestaña *Variables* (no *Secrets*) puedes ajustar, si hace falta:

| Nombre | Por defecto | Cuándo cambiarlo |
| --- | --- | --- |
| `FTP_DIR` | `/public_html/` | pon `/` si la cuenta FTP ya nace dentro de `public_html` |
| `FTP_PROTOCOL` | `ftps` | pon `ftp` solo si el servidor no acepta FTPS |
| `FTP_PORT` | `21` | rara vez |
| `SITE_URL` | `https://explorasiam.com` | — |
| `PUBLIC_GA_ID` | vacío | el `G-…` de Analytics cuando lo tengas |

## Paso 3 · Probar antes de fiarte

En el repo → pestaña **Actions** → *Probar conexión con Raiola* → **Run
workflow**. Entra, lista lo que hay en la carpeta, escribe un archivo de
prueba y lo borra. No toca la web.

- **Conexión correcta** → ya está, el automatismo funciona.
- **La conexión ha fallado** → el resumen dice qué pasó. Lo habitual es que
  falte el `@explorasiam.com` en el usuario, o que haya que probar con
  protocolo `ftp`.
- **Entra, pero no puede escribir** → la cuenta FTP no apunta a esa carpeta.

Puedes lanzar la prueba con otro protocolo, puerto o carpeta sin guardar nada,
para tantear antes de fijar las variables.

## Paso 4 · Publicar

Cualquier cambio en `main` publica. Para forzar una publicación sin cambios:
Actions → *Publicar en Raiola* → **Run workflow**.

Antes de subir nada, el sistema descarga lo que hay en `public_html` y lo
guarda como artefacto durante 90 días, por si hay que volver atrás. Si esa
copia sale vacía, la ejecución lo avisa.

Si faltan las credenciales, la publicación se para en el primer paso con un
mensaje que explica qué falta. No sube nada a medias.

## Qué NO se borra al publicar

La subida no hace limpieza: solo escribe lo que ha cambiado. Así que
sobreviven a cada publicación:

- `public_html/video/` — los vídeos que sube Dani desde el panel
- `public_html/panel/config.php` — su contraseña y el token de GitHub

Por eso el `config.php` está excluido a propósito de la subida: si se
sobrescribiera, el panel se quedaría sin configurar en cada publicación.
