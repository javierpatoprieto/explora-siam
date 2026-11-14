# Accesos y titularidad

Quién es dueño de cada pieza y cómo se traspasa al cliente el día que toque.
**Aquí no se guardan contraseñas.** Las credenciales van en el gestor de contraseñas
de la agencia y, las de despliegue, en los secretos del repositorio.

| Servicio | Para qué | Titular hoy | Cómo se traspasa |
|---|---|---|---|
| Dominio `explorasiam.com` | — | Cliente | Ya es suyo |
| Hosting Raiola (cPanel) | Sirve la web | Cliente | Ya es suyo |
| Repositorio de GitHub | Código y contenido | Agencia | *Settings → Transfer ownership*, conserva el historial |
| Cuenta FTP `deploy` | Publicación automática | Agencia | Se borra y se crea otra si cambia quien mantiene |
| Vercel (si aloja el panel) | Panel de edición | Agencia | Transferir el proyecto o rehacerlo en 10 minutos |
| Google Analytics 4 | Visitas y contactos | Agencia | Añadir al cliente como administrador de la **cuenta**, no solo de la propiedad |
| Google Search Console | Posicionamiento | Agencia | Añadir al cliente como **propietario** desde *Usuarios y permisos* |
| WhatsApp Business | Contacto | Cliente | Ya es suyo |
| Formulario de inscripción | Reservas | Cliente | Ya es suyo (Google Forms) |

## Cómo montarlo pensando en el traspaso

**Analytics.** Crea una **cuenta de Analytics propia para el cliente** (no una propiedad
más dentro de la cuenta de la agencia). En Google Analytics una propiedad no se mueve
entre cuentas con facilidad, pero una cuenta entera se cede añadiendo al cliente como
administrador y quitándote después. Si lo metes dentro de la cuenta de la agencia, el día
de la marcha se pierde el histórico o hay que exportarlo a mano.

**Search Console.** Verifica con **registro TXT en el DNS**, no con la etiqueta HTML.
Motivos: cubre a la vez `http`, `https`, `www` y subdominios; y sigue funcionando aunque
se rehaga la web o se cambie de hosting. La etiqueta HTML se rompe en cuanto alguien
publica una versión sin ella. El campo del panel queda como alternativa si el DNS no está
a mano.

**Enlaza las dos.** En Analytics, *Administrar → Enlaces de productos → Search Console*.
Así las búsquedas por las que entra la gente se ven dentro de Analytics.

**Marca el contacto como conversión.** La web envía el evento `whatsapp_click` cada vez
que alguien pulsa un botón de WhatsApp, con un parámetro `origen` que dice desde qué
bloque salió (hero, ficha del viaje, precio, preguntas, cierre). Dos ajustes en GA4:

1. *Administrar → Eventos clave* → marcar `whatsapp_click`. Es la conversión real del
   negocio: sin esto, Analytics solo cuenta visitas.
2. *Administrar → Definiciones personalizadas → Crear dimensión personalizada*:
   nombre `origen`, ámbito *Evento*, parámetro `origen`. A partir de ahí puedes ver qué
   parte de la página genera más conversaciones y ordenar el contenido en consecuencia.

**Antes de dar por buena la medición**, comprueba en *Informes → Tiempo real* que al
aceptar el aviso de cookies y pulsar un botón de WhatsApp aparece el evento. Si rechazas
las cookies no debe registrarse nada: eso también es señal de que el aviso funciona.
