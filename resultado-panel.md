# Prueba del panel · 2026-11-14 20:53 UTC
commit: 21328d1da013d2b979c7cc49cfc5fa7c96e9b034

== Entrar ==
  OK    rechaza la contraseña mala
  OK    entra con la contraseña buena
  OK    inicio: sin avisos de PHP
== Cada pantalla abre ==
  OK    pantalla textos
  OK    pantalla textos: sin avisos de PHP
  OK    pantalla fotos
  OK    pantalla fotos: sin avisos de PHP
  OK    pantalla videos
  OK    pantalla videos: sin avisos de PHP
  OK    pantalla viaje
  OK    pantalla viaje: sin avisos de PHP
  OK    pantalla preguntas
  OK    pantalla preguntas: sin avisos de PHP
  OK    pantalla secciones
  OK    pantalla secciones: sin avisos de PHP
== Cambiar un texto ==
  OK    el titular se guarda
== Añadir un hueco y poder escribirlo (el fallo que vio Dani) ==
  OK    el hueco se añade
  OK    el hueco nuevo tiene campo donde escribir
  OK    se puede escribir en el hueco
== Mover y quitar de una lista ==
  OK    bajar mueve el elemento
  OK    subir lo devuelve a su sitio
  OK    quitar borra el elegido
== Días de la ruta en moto (lista con foto) ==
  OK    se añade un día
  OK    el día nuevo hereda una foto
  OK    el día nuevo se puede escribir
  OK    se quita el día
== Preguntas ==
  OK    cambia una pregunta
  OK    cambia el orden
  OK    desmarcar «en portada» se guarda
  OK    crea la pregunta nueva
  OK    la nueva sale en portada
  OK    la nueva aparece en la lista
  OK    borra la pregunta
== Secciones ==
  OK    guarda las 12 secciones
  OK    deja encendida la marcada
  OK    apaga las no marcadas
  OK    bajar cambia el orden
  OK    subir lo devuelve
== Viaje ==
  OK    cambia la duración
  OK    las plazas siguen siendo número
  OK    cambia una etapa del itinerario
  OK    cambia un punto fuerte
== Etapas del viaje: añadir, mover y quitar ==
  OK    se añade una etapa
  OK    la etapa nueva queda numerada
  OK    la etapa nueva se puede escribir
  OK    se escribe en la etapa nueva
  OK    subir mueve la etapa
  OK    y se renumeran todas
  OK    se quita la etapa
== Crear y borrar viajes ==
  OK    se crea el viaje
  OK    el archivo toma el nombre del viaje
  OK    nace sin salir en la portada
  OK    nace como próximamente
  OK    hereda la foto (es obligatoria)
  OK    empieza con una etapa en blanco
  OK    aparece en la lista de viajes
  OK    se puede marcar para la portada
  OK    borra el viaje
  OK    no deja borrar el único viaje
== Textos de la página del viaje ==
  OK    cambia el titular del itinerario
  OK    cambia el color principal
== Subir una foto ==
  OK    la foto de prueba es un jpg de verdad
  · dice: Foto cambiada. Textos guardados. La web se actualiza en unos 2 minutos.
  OK    la foto se sube sin error
  OK    la foto nueva se guarda en el repositorio
  OK    la portada apunta a la foto nueva
  OK    guarda el título de la foto
== Rechazar un archivo que no es foto ==
  OK    la imagen rota pasa por jpeg
  OK    avisa de que solo admite imágenes
  . dice: Esa imagen está dañada o es demasiado pequeña. Vuelve a exportarla y súbela otra vez.
  OK    rechaza una imagen rota (si no, la web no se publicaria)
== Subir un vídeo ==
  OK    el vídeo de prueba es un mp4 de verdad
  · carpeta de vídeos: 4096 . 4096 .. 2225 video.mp4 
  · dice: Vídeo subido (0 MB). Guardado. La web se actualiza en unos 2 minutos.
  OK    el vídeo se sube sin error
  OK    el vídeo queda en la carpeta del hosting
  OK    la web apunta al vídeo recién subido
  OK    guarda el título del vídeo
== Sesión y seguridad ==
  OK    sin sesión pide la contraseña
  OK    rechaza el envío sin csrf válido
  OK    y no toca el contenido
  OK    no enseña el token de GitHub
  OK    config.php no se sirve tal cual
== Texto de la landing en la página del viaje ==
  OK    se añade un bloque
  OK    el bloque nuevo se puede escribir
  OK    se guarda el titular del bloque
  OK    se guarda el texto del bloque
  OK    se quita el bloque
== Interruptor de WhatsApp / formulario ==
  OK    se puede volver a WhatsApp
  OK    y dejarlo en formulario
  OK    con su texto de botón
== El panel se actualiza solo ==
  OK    avisa de que hay una versión más nueva
  OK    se pone al día solo desde GitHub
  OK    guarda copia de lo que sustituye
  OK    ya no avisa de versión nueva
  OK    el panel sigue funcionando después

Guardados registrados (los últimos):
PUT content/home.json :: Panel: foto de Portada · Foto de fondo en ordenador
PUT content/fotos.json :: Panel: textos de la foto foto-261114205342.jpg
PUT content/home.json :: Panel: vídeo de El viaje
PUT content/salidas/mae-hong-son-loop-nov-2026.json :: Panel: datos del viaje
PUT content/salidas/mae-hong-son-loop-nov-2026.json :: Panel: datos del viaje
PUT content/salidas/mae-hong-son-loop-nov-2026.json :: Panel: datos del viaje
PUT content/site.json :: Panel: datos de contacto
PUT content/site.json :: Panel: datos de contacto

RESULTADO: todo correcto

## Compilar la web con lo que escribió el panel
compila correctamente

## Botones de contacto en la web compilada
portada → enlaces a WhatsApp: 0 · al formulario: 5

## Registro del servidor
[Sat Nov 14 20:53:39 2026] PHP Warning:  JIT is incompatible with third party extensions that override zend_execute_ex(). JIT disabled. in Unknown on line 0
