# Analytics, Search Console y Perfil de Empresa

Los tres hay que crearlos entrando con tu cuenta de Google. Aquí está todo lo
que hay que pegar en cada sitio, ya redactado, para que sea seguir la lista.

Créalos **con tu cuenta**, y más adelante añade a Dani como usuario (al final
de este documento).

---

## 1. Google Analytics 4

En [analytics.google.com](https://analytics.google.com) → Administrar → Crear
propiedad.

| Campo | Valor |
| --- | --- |
| Nombre de la propiedad | `Explora Siam` |
| Zona horaria | España (GMT+1) |
| Moneda | Euro (€) |
| Sector | Viajes |
| Tamaño de empresa | Pequeña (1-10) |
| Objetivo | Generar clientes potenciales |

Luego crea un flujo de datos **Web** con URL `https://explorasiam.com` y
nombre `Web Explora Siam`. Te dará un identificador con la forma `G-XXXXXXXXXX`.

**Ese código va en el panel**, no en GitHub: entra en `explorasiam.com/panel` →
Textos → campo *Google Analytics (G-...)*, pégalo y guarda. La web lo recoge
sola en un par de minutos.

### Ajustes que sí merecen la pena

En Administrar → **Configuración de datos → Retención de datos**, súbelo de 2 a
**14 meses**. Viene en 2 por defecto y es lo primero que se echa de menos.

En **Eventos → Marcar como evento clave**, marca `whatsapp_click`. Es el evento
que ya dispara la web cada vez que alguien pulsa un botón de WhatsApp: es la
conversión real del negocio, porque no hay carrito ni formulario de pago.

En **Definiciones personalizadas → Crear dimensión personalizada**:

| Campo | Valor |
| --- | --- |
| Nombre | `Origen del WhatsApp` |
| Ámbito | Evento |
| Parámetro | `origen` |

Con eso verás desde qué parte de la página sale cada contacto (el botón del
menú, el del hero, el flotante, el del precio…), que es lo que dice qué sección
está funcionando.

### Una advertencia sobre los datos

La analítica **solo se carga si el visitante acepta las cookies**, porque el
aviso está hecho para cumplir el RGPD. Eso significa que Analytics va a
enseñar menos visitas de las que hay en realidad: es normal y es lo correcto
legalmente. No lo interpretes como que la web tiene poco tráfico.

---

## 2. Search Console

En [search.google.com/search-console](https://search.google.com/search-console)
→ Añadir propiedad.

Elige **Dominio** (la columna de la izquierda), no «Prefijo de URL». Escribe
`explorasiam.com`. Cubre el dominio entero, con y sin `www`, http y https.

Te pedirá crear un registro **TXT** en el DNS. Ese registro se añade en el
panel de Raiola, en la zona DNS de `explorasiam.com`:

| Campo | Valor |
| --- | --- |
| Tipo | TXT |
| Nombre / Host | `@` (o vacío, según lo pida Raiola) |
| Valor | el `google-site-verification=...` que te dé Google |
| TTL | el que venga por defecto |

Tarda entre unos minutos y unas horas en propagarse. Si prefieres no tocar el
DNS, elige «Prefijo de URL» con `https://explorasiam.com` y usa la opción de
etiqueta HTML: en ese caso el código va en el panel → Textos → *Verificación de
Google Search Console*. La verificación por dominio es mejor, pero las dos
valen.

### Nada más verificarlo

Envía el sitemap: Search Console → **Sitemaps** → escribe `sitemap-index.xml` y
pulsa Enviar. La web ya lo genera sola en cada publicación.

No esperes datos el primer día. Search Console tarda dos o tres días en
enseñar algo y varias semanas en ser útil.

---

## 3. Perfil de Empresa (Google Business Profile)

En [business.google.com](https://business.google.com) → Añadir empresa.

**Antes de empezar, algo que conviene saber.** El Perfil de Empresa está pensado
para negocios con presencia física o con zona de servicio, y Google verifica que
existen: normalmente pide un **vídeo** grabado en directo enseñando el local,
material de la empresa o documentación. No siempre lo aprueba a la primera con
negocios que operan sin oficina de cara al público. Merece la pena intentarlo
—aparecer en Maps y en el panel lateral de Google vale mucho— pero que no te
pille por sorpresa si pide más pruebas. Que Dani tenga a mano el alta de
autónomo o la documentación de la agencia.

### Datos del perfil

| Campo | Valor |
| --- | --- |
| Nombre | `Explora Siam` |
| Categoría principal | Agencia de viajes |
| Categorías secundarias | Operador turístico · Agencia de viajes de aventura |
| ¿Tienes tienda física? | No |
| Zona de servicio | Santander, Cantabria (y España, si lo permite) |
| Teléfono | +34 605 330 654 |
| Web | https://explorasiam.com |
| Horario | Lunes a viernes, 9:00 – 20:00 |

### Descripción (cabe en el límite de 750 caracteres)

```
Viajes en moto por el norte de Tailandia en grupos de 6 a 8 personas.
Organizamos el Mae Hong Son Loop: 650 km y más de 1.800 curvas entre montañas,
arrozales y pueblos de montaña, con guía local certificado por la TAT y
furgoneta de apoyo que lleva el equipaje de etapa en etapa.

La ruta empieza en Bangkok y sigue por Chiang Rai, la frontera con Laos, Chiang
Mai y seis días de curvas hasta Pai. Grupos pequeños, a un ritmo que deja sitio
a lo que pase por el camino.

Dani, el fundador, lleva años recorriendo el país en moto y acompaña
personalmente cada salida. Escríbenos por WhatsApp y te cuenta sin compromiso
si el viaje encaja contigo.
```

### Servicios

- Mae Hong Son Loop en moto — 17 días
- Viajes en moto en grupo reducido
- Rutas guiadas por el norte de Tailandia
- Asesoramiento de viaje personalizado

### Atributos que activar

Identificado como propiedad de veteranos: no. Lo que sí conviene marcar:
*se requiere cita previa*, *atención por videollamada* y los idiomas
(**español** e **inglés**).

### Fotos

Sube las mismas de la web, que ya están optimizadas: el logo como foto de
perfil, `moto-curva-169.jpg` como portada, y luego `phuchifa-169.jpg`,
`linternas-45.jpg`, `budas-dorados.jpg` y `templo-chiang-mai.jpg`. Cuando Dani
mande su retrato bueno, ese va como foto del equipo.

Google prioriza los perfiles con fotos recientes: decirle a Dani que suba una
o dos al volver de cada viaje es de las cosas que más mueven la aguja.

### La primera publicación

El perfil deja publicar novedades. La primera, para que no nazca vacío:

```
Plazas abiertas para el Mae Hong Son Loop, del 21 de noviembre al 7 de
diciembre de 2026. Diecisiete días de Bangkok a Pai en grupo de 6 a 8 personas,
con guía local y furgoneta de apoyo. Escríbenos por WhatsApp y te contamos.
```

Botón: *Más información* → `https://explorasiam.com`

---

## 4. Dar acceso a Dani

Cuando esté todo montado y funcionando, y no antes:

| Dónde | Cómo | Qué permiso |
| --- | --- | --- |
| Analytics | Administrar → Acceso a la propiedad → + | Analista (ve los datos, no los toca) |
| Search Console | Configuración → Usuarios y permisos → Añadir | Completo o Restringido |
| Perfil de Empresa | Configuración → Administradores → Añadir | Administrador |

Deja el perfil de empresa a su nombre a la larga: es de su negocio, y si algún
día dejáis de trabajar juntos, que no dependa de tu cuenta. Lo mismo vale para
el dominio y el hosting.

---

## Resumen de lo que hay que pegar en el panel

De todo lo anterior, solo dos cosas acaban en la web:

| Panel → Textos | De dónde sale |
| --- | --- |
| *Google Analytics (G-...)* | el `G-XXXXXXXXXX` del flujo de datos web |
| *Verificación de Google Search Console* | solo si verificas por etiqueta HTML en vez de por DNS |

Pega, guarda, y la web se publica sola.
