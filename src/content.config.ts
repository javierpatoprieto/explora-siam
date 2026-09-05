import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

const site = defineCollection({
  loader: glob({ pattern: 'site.json', base: './content' }),
  schema: z.object({
    marca: z.string(),
    fundador: z.string(),
    whatsapp: z.string(),
    email: z.string(),
    instagram: z.string().url(),
    ubicacion: z.string(),
    licencia: z.string().optional().default(''),
    horario: z.string().optional().default(''),
    indexar: z.boolean().default(false),
    formulario: z.string().optional().default(''),
    seoTitulo: z.string(),
    seoDescripcion: z.string(),
    plantillas: z.record(z.string()),
  }),
});

const home = defineCollection({
  loader: glob({ pattern: 'home.json', base: './content' }),
  schema: ({ image }) =>
    z.object({
      hero: z.object({
        badge: z.string(),
        titulo: z.string(),
        destacado: z.string().optional().default(''),
        subtitulo: z.string(),
        botonPrimario: z.string(),
        microcopy: z.string().optional().default(''),
        botonSecundario: z.string().optional().default(''),
        poster: image(),
        posterMovil: image(),
        videoMp4: z.string().optional().default(''),
        videoWebm: z.string().optional().default(''),
        mostrarProximaSalida: z.boolean().default(true),
        cinta: z.array(z.string()).default([]),
      }),
      cifras: z.array(z.object({ valor: z.string(), unidad: z.string().optional().default(''), etiqueta: z.string() })).default([]),
      ruta: z.object({ etiqueta: z.string(), titulo: z.string(), imagenes: z.array(image()), pies: z.array(z.string()).default([]) }),
      precio: z.object({ etiqueta: z.string(), titulo: z.string(), nota: z.string(), pagos: z.array(z.object({ importe: z.string(), cuando: z.string() })) }),
      hechos: z.array(z.string()),
      manifiesto: z.object({
        titulo: z.string(),
        destacado: z.string().optional().default(''),
        cierre: z.string().optional().default(''),
        bloques: z.array(z.object({ titulo: z.string(), texto: z.string() })),
        imagen: image(),
        pie: z.string().optional().default(''),
        enlace: z.string().optional().default(''),
      }),
      salidas: z.object({ etiqueta: z.string(), titulo: z.string(), subtitulo: z.string() }),
      video: z.object({ etiqueta: z.string(), titulo: z.string(), poster: image(), mp4: z.string().optional().default('') }),
      dia: z.object({
        etiqueta: z.string(),
        titulo: z.string(),
        paradas: z.array(z.object({ hora: z.string(), titulo: z.string(), texto: z.string(), imagen: image() })),
        cierre: z.string(),
        cierreBoton: z.string(),
      }),
      fundador: z.object({
        etiqueta: z.string(),
        titulo: z.string(),
        parrafos: z.array(z.string()),
        retrato: image(),
        retratoPie: z.string().optional().default(''),
        archivo: image().optional(),
        archivoPie: z.string().optional().default(''),
        datos: z.array(z.string()),
        boton: z.string(),
        microcopy: z.string().optional().default(''),
      }),
      galeria: z.object({
        etiqueta: z.string(),
        intro: z.string().optional().default(''),
        piezas: z.array(
          z.object({
            imagen: image(),
            pie: z.string().optional().default(''),
            tamano: z.enum(['alto', 'medio', 'pequeno', 'sangre', 'tercio']).default('tercio'),
            video: z.boolean().default(false),
          }),
        ),
        enlaceInstagram: z.string().optional().default('Más en Instagram'),
      }),
      testimonios: z.object({ titulo: z.string(), fondo: image() }),
      incluye: z.object({
        titulo: z.string(),
        microcopy: z.string().optional().default(''),
        incluido: z.array(z.string()),
        noIncluido: z.array(z.string()),
        enlace: z.string().optional().default(''),
      }),
      faq: z.object({ titulo: z.string(), intro: z.string(), enlace: z.string(), verTodas: z.string() }),
      cta: z.object({ titulo: z.string(), subtitulo: z.string(), boton: z.string(), microcopy: z.string().optional().default(''), imagen: image() }),
      footer: z.object({ frase: z.string() }),
    }),
});

const salidas = defineCollection({
  loader: glob({ pattern: '*.json', base: './content/salidas' }),
  schema: ({ image }) =>
    z.object({
      nombre: z.string(),
      slug: z.string(),
      fechas: z.string(),
      inicio: z.string(),
      duracion: z.string(),
      plazas: z.number(),
      disponibles: z.number(),
      estado: z.enum(['abierta', 'ultimas', 'completa', 'proximamente']),
      precio: z.number(),
      notaPrecio: z.string().optional().default(''),
      highlights: z.array(z.string()).default([]),
      imagen: image(),
      descripcion: z.string(),
      itinerario: z.array(z.object({ dia: z.number(), titulo: z.string(), texto: z.string() })).default([]),
      orden: z.number().default(99),
      destacada: z.boolean().default(true),
    }),
});

const testimonios = defineCollection({
  loader: glob({ pattern: '*.json', base: './content/testimonios' }),
  schema: ({ image }) =>
    z.object({
      cita: z.string(),
      nombre: z.string(),
      ciudad: z.string().optional().default(''),
      viaje: z.string().optional().default(''),
      fecha: z.string().optional().default(''),
      foto: image().optional(),
      destacado: z.boolean().default(false),
      orden: z.number().default(99),
    }),
});

const faqs = defineCollection({
  loader: glob({ pattern: '*.json', base: './content/faqs' }),
  schema: z.object({ pregunta: z.string(), respuesta: z.string(), enHome: z.boolean().default(false), orden: z.number().default(99) }),
});

const legal = defineCollection({
  loader: glob({ pattern: '*.md', base: './content/legal' }),
  schema: z.object({ titulo: z.string() }),
});

export const collections = { site, home, salidas, testimonios, faqs, legal };
