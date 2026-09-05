import { config, fields, collection, singleton } from '@keystatic/core';

const github = Boolean(process.env.KEYSTATIC_GITHUB_CLIENT_ID);
const img = (publicPath: string, label: string, description?: string) =>
  fields.image({ label, description, directory: 'src/assets/img', publicPath });
const IMG_HOME = '../src/assets/img/';
const IMG_COL = '../../src/assets/img/';

export default config({
  storage: github ? { kind: 'github', repo: { owner: 'javierpatoprieto', name: 'explora-siam' } } : { kind: 'local' },
  ui: { brand: { name: 'Explora Siam' }, navigation: { Web: ['home', 'site'], Contenido: ['salidas', 'testimonios', 'faqs'] } },
  singletons: {
    site: singleton({
      label: 'Datos del sitio',
      path: 'content/site',
      format: { data: 'json' },
      schema: {
        marca: fields.text({ label: 'Nombre de la marca' }),
        fundador: fields.text({ label: 'Nombre del anfitrión' }),
        whatsapp: fields.text({ label: 'WhatsApp (internacional, sin +)', description: 'Ejemplo: 34605330654' }),
        email: fields.text({ label: 'Email' }),
        instagram: fields.url({ label: 'Instagram' }),
        ubicacion: fields.text({ label: 'Ubicación (pie de página)' }),
        licencia: fields.text({ label: 'Licencia o registro de agencia' }),
        horario: fields.text({ label: 'Horario de atención' }),
        indexar: fields.checkbox({ label: 'Permitir que Google indexe la web', defaultValue: false }),
        formulario: fields.url({ label: 'Formulario de inscripción (opcional)', description: 'Si está vacío no se muestra el enlace.' }),
        seoTitulo: fields.text({ label: 'Título SEO' }),
        seoDescripcion: fields.text({ label: 'Descripción SEO', multiline: true }),
        plantillas: fields.object(
          Object.fromEntries(
            ['nav', 'hero', 'salida', 'avisame', 'dia', 'daniel', 'galeria', 'precio', 'faq', 'final', 'flotante'].map((k) => [
              k,
              fields.text({ label: `Mensaje de WhatsApp · ${k}`, multiline: true }),
            ]),
          ),
          { label: 'Mensajes predefinidos de WhatsApp', description: 'En "salida" y "avisame" puedes usar {nombre} y {fechas}.' },
        ),
      },
    }),
    home: singleton({
      label: 'Home',
      path: 'content/home',
      format: { data: 'json' },
      schema: {
        hero: fields.object(
          {
            badge: fields.text({ label: 'Etiqueta superior' }),
            titulo: fields.text({ label: 'Titular' }),
            destacado: fields.text({ label: 'Parte del titular en coral' }),
            subtitulo: fields.text({ label: 'Subtítulo', multiline: true }),
            botonPrimario: fields.text({ label: 'Botón de WhatsApp' }),
            microcopy: fields.text({ label: 'Texto bajo el botón' }),
            botonSecundario: fields.text({ label: 'Botón secundario' }),
            poster: img(IMG_HOME, 'Imagen de fondo (escritorio)'),
            posterMovil: img(IMG_HOME, 'Imagen de fondo (móvil, vertical)'),
            videoMp4: fields.text({ label: 'Vídeo MP4 (ruta en /public/video/ o URL)' }),
            videoWebm: fields.text({ label: 'Vídeo WebM (opcional)' }),
            mostrarProximaSalida: fields.checkbox({ label: 'Mostrar la próxima salida en el hero', defaultValue: true }),
            cinta: fields.array(fields.text({ label: 'Lugar' }), { label: 'Cinta de lugares (pie del hero)', itemLabel: (p) => p.value }),
          },
          { label: 'Hero' },
        ),
        cifrasTexto: fields.object({ titulo: fields.text({ label: 'Titular' }), texto: fields.text({ label: 'Texto', multiline: true }) }, { label: 'Bloque de cifras · texto' }),
        statement: fields.object(
          {
            antes: fields.text({ label: 'Frase (inicio)' }),
            imagen1: img(IMG_HOME, 'Imagen 1'),
            medio: fields.text({ label: 'Frase (medio)' }),
            imagen2: img(IMG_HOME, 'Imagen 2'),
            despues: fields.text({ label: 'Frase (final)' }),
            texto: fields.text({ label: 'Texto pequeño' }),
          },
          { label: 'Frase con imágenes' },
        ),
        ventajas: fields.object(
          {
            titulo: fields.text({ label: 'Titular' }),
            texto: fields.text({ label: 'Texto' }),
            items: fields.array(
              fields.object({
                icono: fields.select({ label: 'Icono', options: [{ label: 'Guía', value: 'guia' }, { label: 'Furgoneta', value: 'furgo' }, { label: 'Grupo', value: 'grupo' }, { label: 'Seguro', value: 'seguro' }], defaultValue: 'guia' }),
                titulo: fields.text({ label: 'Título' }),
                texto: fields.text({ label: 'Texto' }),
              }),
              { label: 'Ventajas', itemLabel: (p) => p.fields.titulo.value },
            ),
          },
          { label: 'Ventajas' },
        ),
        cifras: fields.array(
          fields.object({ valor: fields.text({ label: 'Cifra' }), unidad: fields.text({ label: 'Unidad' }), etiqueta: fields.text({ label: 'Texto' }) }),
          { label: 'Cifras gigantes', itemLabel: (p) => `${p.fields.valor.value} ${p.fields.unidad.value}` },
        ),
        ruta: fields.object(
          {
            etiqueta: fields.text({ label: 'Etiqueta' }),
            titulo: fields.text({ label: 'Titular' }),
            imagenes: fields.array(img(IMG_HOME, 'Imagen'), { label: 'Una imagen por etapa (en el orden del itinerario del viaje)' }),
            pies: fields.array(fields.text({ label: 'Pie' }), { label: 'Pies de foto', itemLabel: (p) => p.value }),
          },
          { label: 'La ruta, etapa a etapa' },
        ),
        precio: fields.object(
          {
            etiqueta: fields.text({ label: 'Etiqueta' }),
            titulo: fields.text({ label: 'Precio (texto grande)' }),
            nota: fields.text({ label: 'Nota' }),
            pagos: fields.array(fields.object({ importe: fields.text({ label: 'Importe' }), cuando: fields.text({ label: 'Cuándo' }) }), { label: 'Pagos', itemLabel: (p) => `${p.fields.importe.value} ${p.fields.cuando.value}` }),
          },
          { label: 'Precio' },
        ),
        hechos: fields.array(fields.text({ label: 'Hecho' }), { label: 'Franja de hechos', itemLabel: (p) => p.value }),
        manifiesto: fields.object(
          {
            titulo: fields.text({ label: 'Titular (inicio)' }),
            destacado: fields.text({ label: 'Parte en coral' }),
            cierre: fields.text({ label: 'Titular (final)' }),
            bloques: fields.array(
              fields.object({ titulo: fields.text({ label: 'Título' }), texto: fields.text({ label: 'Texto', multiline: true }) }),
              { label: 'Bloques', itemLabel: (p) => p.fields.titulo.value },
            ),
            imagen: img(IMG_HOME, 'Imagen'),
            pie: fields.text({ label: 'Pie de foto' }),
            enlace: fields.text({ label: 'Texto del enlace a la galería' }),
          },
          { label: 'Manifiesto' },
        ),
        salidas: fields.object(
          { etiqueta: fields.text({ label: 'Etiqueta' }), titulo: fields.text({ label: 'Titular' }), subtitulo: fields.text({ label: 'Subtítulo' }) },
          { label: 'Cabecera de salidas' },
        ),
        video: fields.object(
          {
            etiqueta: fields.text({ label: 'Etiqueta' }),
            titulo: fields.text({ label: 'Titular' }),
            poster: img(IMG_HOME, 'Fotograma de portada'),
            mp4: fields.text({ label: 'Vídeo MP4 (ruta en /public/video/ o URL)' }),
          },
          { label: 'Banda de vídeo' },
        ),
        dia: fields.object(
          {
            etiqueta: fields.text({ label: 'Etiqueta' }),
            titulo: fields.text({ label: 'Titular' }),
            paradas: fields.array(
              fields.object({
                hora: fields.text({ label: 'Hora' }),
                titulo: fields.text({ label: 'Título' }),
                texto: fields.text({ label: 'Texto' }),
                imagen: img(IMG_HOME, 'Imagen'),
              }),
              { label: 'Paradas', itemLabel: (p) => `${p.fields.hora.value} · ${p.fields.titulo.value}` },
            ),
            cierre: fields.text({ label: 'Texto de la tarjeta final' }),
            cierreBoton: fields.text({ label: 'Botón de la tarjeta final' }),
          },
          { label: 'Un martes cualquiera' },
        ),
        fundador: fields.object(
          {
            etiqueta: fields.text({ label: 'Etiqueta' }),
            titulo: fields.text({ label: 'Titular' }),
            parrafos: fields.array(fields.text({ label: 'Párrafo', multiline: true }), { label: 'Carta', itemLabel: (p) => p.value.slice(0, 60) }),
            retrato: img(IMG_HOME, 'Retrato'),
            retratoPie: fields.text({ label: 'Pie del retrato (vacío para ocultar)' }),
            archivo: img(IMG_HOME, 'Foto de archivo (opcional)'),
            archivoPie: fields.text({ label: 'Pie de la foto de archivo' }),
            datos: fields.array(fields.text({ label: 'Dato' }), { label: 'Datos breves', itemLabel: (p) => p.value }),
            boton: fields.text({ label: 'Botón' }),
            microcopy: fields.text({ label: 'Texto junto al botón' }),
          },
          { label: 'Quién te acompaña' },
        ),
        galeria: fields.object(
          {
            etiqueta: fields.text({ label: 'Etiqueta' }),
            intro: fields.text({ label: 'Texto de introducción (opcional)', multiline: true }),
            piezas: fields.array(
              fields.object({
                imagen: img(IMG_HOME, 'Imagen'),
                pie: fields.text({ label: 'Pie de foto' }),
                tamano: fields.select({
                  label: 'Tamaño',
                  options: [
                    { label: 'Alto (vertical grande)', value: 'alto' },
                    { label: 'Medio', value: 'medio' },
                    { label: 'Pequeño', value: 'pequeno' },
                    { label: 'A sangre (todo el ancho)', value: 'sangre' },
                    { label: 'Tercio', value: 'tercio' },
                  ],
                  defaultValue: 'tercio',
                }),
                video: fields.checkbox({ label: 'Es un vídeo (muestra el botón de reproducir)' }),
              }),
              { label: 'Piezas', itemLabel: (p) => p.fields.pie.value || 'Imagen' },
            ),
            enlaceInstagram: fields.text({ label: 'Texto del enlace a Instagram' }),
          },
          { label: 'Galería' },
        ),
        testimonios: fields.object({ titulo: fields.text({ label: 'Titular' }), fondo: img(IMG_HOME, 'Foto de fondo del testimonio destacado') }, { label: 'Testimonios' }),
        incluye: fields.object(
          {
            titulo: fields.text({ label: 'Titular' }),
            microcopy: fields.text({ label: 'Texto' }),
            incluido: fields.array(fields.text({ label: 'Incluido' }), { label: 'Incluido', itemLabel: (p) => p.value }),
            noIncluido: fields.array(fields.text({ label: 'No incluido' }), { label: 'No incluido', itemLabel: (p) => p.value }),
            enlace: fields.text({ label: 'Texto del enlace a WhatsApp' }),
          },
          { label: 'Qué incluye' },
        ),
        faq: fields.object(
          {
            titulo: fields.text({ label: 'Titular' }),
            intro: fields.text({ label: 'Introducción', multiline: true }),
            enlace: fields.text({ label: 'Texto del enlace a WhatsApp' }),
            verTodas: fields.text({ label: 'Texto del enlace a todas las preguntas' }),
          },
          { label: 'Preguntas' },
        ),
        cta: fields.object(
          {
            titulo: fields.text({ label: 'Titular' }),
            subtitulo: fields.text({ label: 'Subtítulo', multiline: true }),
            boton: fields.text({ label: 'Botón' }),
            microcopy: fields.text({ label: 'Texto bajo el botón' }),
            imagen: img(IMG_HOME, 'Imagen de fondo'),
          },
          { label: 'CTA final' },
        ),
        footer: fields.object({ frase: fields.text({ label: 'Frase de cierre' }) }, { label: 'Pie de página' }),
      },
    }),
  },
  collections: {
    salidas: collection({
      label: 'Salidas',
      slugField: 'slug',
      path: 'content/salidas/*',
      format: { data: 'json' },
      schema: {
        nombre: fields.text({ label: 'Nombre del viaje' }),
        slug: fields.slug({ name: { label: 'Identificador (URL)' } }),
        fechas: fields.text({ label: 'Fechas (texto)', description: 'Ejemplo: 12 – 26 nov 2026' }),
        inicio: fields.date({ label: 'Fecha de inicio' }),
        duracion: fields.text({ label: 'Duración', description: 'Ejemplo: 15 días' }),
        plazas: fields.integer({ label: 'Plazas totales', defaultValue: 12 }),
        disponibles: fields.integer({ label: 'Plazas disponibles', defaultValue: 12 }),
        estado: fields.select({
          label: 'Estado',
          options: [
            { label: 'Abierta', value: 'abierta' },
            { label: 'Últimas plazas', value: 'ultimas' },
            { label: 'Grupo completo', value: 'completa' },
            { label: 'Próximamente', value: 'proximamente' },
          ],
          defaultValue: 'abierta',
        }),
        precio: fields.integer({ label: 'Precio desde (€)' }),
        notaPrecio: fields.text({ label: 'Nota de precio' }),
        highlights: fields.array(fields.text({ label: 'Highlight' }), { label: 'Tres highlights', itemLabel: (p) => p.value }),
        imagen: img(IMG_COL, 'Imagen principal (vertical)'),
        descripcion: fields.text({ label: 'Descripción corta', multiline: true }),
        itinerario: fields.array(
          fields.object({ dia: fields.integer({ label: 'Día' }), titulo: fields.text({ label: 'Título' }), texto: fields.text({ label: 'Texto', multiline: true }) }),
          { label: 'Itinerario', itemLabel: (p) => `Día ${p.fields.dia.value} · ${p.fields.titulo.value}` },
        ),
        orden: fields.integer({ label: 'Orden', defaultValue: 1 }),
        destacada: fields.checkbox({ label: 'Mostrar en la home', defaultValue: true }),
      },
    }),
    testimonios: collection({
      label: 'Testimonios',
      slugField: 'nombre',
      path: 'content/testimonios/*',
      format: { data: 'json' },
      schema: {
        cita: fields.text({ label: 'Cita', multiline: true }),
        nombre: fields.slug({ name: { label: 'Nombre' } }),
        ciudad: fields.text({ label: 'Ciudad' }),
        viaje: fields.text({ label: 'Viaje' }),
        fecha: fields.text({ label: 'Fecha (texto)' }),
        foto: img(IMG_COL, 'Foto del viajero en Tailandia (opcional)'),
        destacado: fields.checkbox({ label: 'Testimonio destacado' }),
        orden: fields.integer({ label: 'Orden', defaultValue: 1 }),
      },
    }),
    faqs: collection({
      label: 'Preguntas',
      slugField: 'pregunta',
      path: 'content/faqs/*',
      format: { data: 'json' },
      schema: {
        pregunta: fields.slug({ name: { label: 'Pregunta (tal como la escribiría un viajero)' } }),
        respuesta: fields.text({ label: 'Respuesta', multiline: true }),
        enHome: fields.checkbox({ label: 'Mostrar en la home' }),
        orden: fields.integer({ label: 'Orden', defaultValue: 1 }),
      },
    }),
  },
});
