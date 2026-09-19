<?php
declare(strict_types=1);

/**
 * Panel de Explora Siam.
 * Edita los textos, las fotos y los vídeos de la web. Cada cambio se guarda en el
 * repositorio y Vercel vuelve a publicar la web solo, en unos minutos.
 */

if (!file_exists(__DIR__ . '/config.php')) {
    exit('Falta config.php. Copia config.example.php como config.php y rellena los datos.');
}
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

// Evita dejar el panel abierto con los valores de ejemplo.
if (PANEL_PASSWORD === 'cambia-esta-contrasena' || str_starts_with(GITHUB_TOKEN, 'github_pat_...')) {
    exit('Panel sin configurar: edita panel/config.php y pon la contraseña y el token de GitHub.');
}

panel_arrancar();

const HOME = 'content/home.json';
const SITIO = 'content/site.json';
const IMGS = 'src/assets/img';
const TEXTOS_FOTOS = 'content/fotos.json';
const VIDEOS = 'public/video';

// La web vive en Vercel y no ve la carpeta public_html/video del hosting, asi
// que los vídeos se sirven por un subdominio propio que apunta a esa carpeta.
// Sin esto, el vídeo se sube bien pero la web nunca lo encuentra.
const URL_VIDEOS = 'https://media.explorasiam.com';

/* ---------------------------------------------------------------- salir */
if (isset($_GET['salir'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

/* ------------------------------------------------------------- entrada */
$aviso = null;
if (!dentro()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clave'])) {
        $espera = bloqueado();
        if ($espera > 0) {
            $aviso = ['mal', 'Demasiados intentos. Prueba dentro de ' . ceil($espera / 60) . ' minutos.'];
        } elseif (hash_equals(PANEL_PASSWORD, (string) $_POST['clave'])) {
            limpiar_fallos();
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: index.php');
            exit;
        } else {
            apuntar_fallo();
            $aviso = ['mal', 'Contraseña incorrecta.'];
        }
    }
    pintar_entrada($aviso);
    exit;
}

/* ------------------------------------------------- imágenes del repositorio */
// Vista previa de las fotos. GitHub solo devuelve el contenido por la API normal
// si pesa menos de 1 MB (las fotos de Dani pesan 4-7 MB y salían rotas), así que
// se piden en crudo y se sirve una miniatura ligera, guardada para la próxima vez.
// Los nombres llevan fecha y hora, así que una foto nueva nunca pisa una miniatura vieja.
if (isset($_GET['foto'])) {
    $nombre = basename((string) $_GET['foto']);
    $cache = __DIR__ . '/.miniaturas/' . sha1($nombre) . '.jpg';
    if (!is_file($cache)) {
        $crudo = leer_foto_cruda(IMGS . '/' . $nombre);
        if ($crudo === null) {
            http_response_code(404);
            exit;
        }
        $mini = miniatura($crudo, 900);
        if ($mini === null) {
            // GD no la entiende: se sirve tal cual.
            header('Content-Type: ' . (str_ends_with($nombre, '.png') ? 'image/png' : (str_ends_with($nombre, '.webp') ? 'image/webp' : 'image/jpeg')));
            header('Cache-Control: private, max-age=300');
            echo $crudo;
            exit;
        }
        if (!is_dir(dirname($cache))) {
            @mkdir(dirname($cache), 0755, true);
        }
        @file_put_contents($cache, $mini);
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=86400');
        echo $mini;
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=86400');
    readfile($cache);
    exit;
}

/** Descarga un archivo del repositorio en crudo (vale hasta 100 MB). */
function leer_foto_cruda(string $ruta): ?string
{
    $ch = curl_init('https://api.github.com' . repo() . '/contents/' . rawurlencode_ruta($ruta) . '?ref=' . GITHUB_BRANCH);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . GITHUB_TOKEN,
            'Accept: application/vnd.github.raw',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: panel-explora-siam',
        ],
    ]);
    $datos = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($codigo === 200 && is_string($datos) && $datos !== '') ? $datos : null;
}

/** Reduce una imagen a $lado píxeles como mucho y la devuelve en JPEG, derecha según EXIF. */
function miniatura(string $crudo, int $lado): ?string
{
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }
    $img = @imagecreatefromstring($crudo);
    if ($img === false) {
        return null;
    }
    if (function_exists('exif_read_data') && str_starts_with($crudo, "\xFF\xD8")) {
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($crudo));
        $giro = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($giro !== 0) {
            $girada = imagerotate($img, $giro, 0);
            if ($girada !== false) {
                imagedestroy($img);
                $img = $girada;
            }
        }
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $f = min(1, $lado / max($w, $h));
    $nw = max(1, (int) round($w * $f));
    $nh = max(1, (int) round($h * $f));
    $mini = imagecreatetruecolor($nw, $nh);
    imagefill($mini, 0, 0, imagecolorallocate($mini, 255, 255, 255)); // fondo para los PNG transparentes
    imagecopyresampled($mini, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    ob_start();
    imagejpeg($mini, null, 82);
    imagedestroy($mini);
    return (string) ob_get_clean();
}

/* ------------------------------------------------ estado de la web en Vercel */
// La web la publica Vercel sola en cada cambio del repositorio. Aquí solo
// preguntamos a GitHub cómo va el despliegue del último cambio, para que la
// página pueda decir "publicando" o "publicado". El panel ya no copia la web
// al hosting: eso era de antes de Vercel y era lo que daba errores.
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ((string) $_GET['api'] === 'estado') {
        echo json_encode(estado_web());
        exit;
    }
    http_response_code(400);
    echo json_encode(['estado' => 'error', 'mensaje' => 'Acción desconocida.']);
    exit;
}

/**
 * Estado del despliegue del último cambio de la rama publicada.
 * Vercel deja su resultado en GitHub como "status" del commit.
 */
function estado_web(): array
{
    [$c1, $commit] = gh('GET', repo() . '/commits/' . GITHUB_BRANCH);
    if ($c1 !== 200) {
        return ['estado' => 'desconocido', 'mensaje' => 'No se ha podido preguntar a GitHub.'];
    }
    $sha = (string) ($commit['sha'] ?? '');
    $cuando = (string) ($commit['commit']['committer']['date'] ?? '');
    [$c2, $lista] = gh('GET', repo() . '/commits/' . $sha . '/statuses?per_page=30');
    $vercel = array_values(array_filter(
        $c2 === 200 && is_array($lista) ? $lista : [],
        fn ($s) => stripos((string) ($s['context'] ?? ''), 'vercel') !== false
    ));
    $estados = array_map(fn ($s) => (string) ($s['state'] ?? ''), $vercel);
    if (in_array('success', $estados, true)) {
        $estado = 'al-dia';
    } elseif (in_array('pending', $estados, true) || !$estados) {
        // Sin noticias aún: Vercel a veces tarda en recoger el cambio o lo tiene en cola.
        $estado = 'publicando';
    } else {
        $estado = 'error';
    }
    return [
        'estado' => $estado,
        'commit' => substr($sha, 0, 7),
        'minutos' => $cuando !== '' ? max(0, (int) floor((time() - strtotime($cuando)) / 60)) : null,
        'mensaje' => $estado === 'error' ? 'Vercel no ha podido publicar el último cambio.' : '',
    ];
}

/* ---------------------------------------------------- campos de texto */
$CAMPOS = [
    'Portada' => [
        ['home', 'hero.badge', 'Etiqueta pequeña', 'linea'],
        ['home', 'hero.titulo', 'Titular', 'linea'],
        ['home', 'hero.destacado', 'Final del titular (en color)', 'linea'],
        ['home', 'hero.subtitulo', 'Texto bajo el titular', 'parrafo'],
        ['home', 'hero.botonPrimario', 'Botón de WhatsApp', 'linea'],
        ['home', 'hero.microcopy', 'Frase bajo el botón', 'linea'],
    ],
    '¿Y si esta vez…?' => [
        ['home', 'manifiesto.titulo', 'Titular, inicio', 'linea'],
        ['home', 'manifiesto.destacado', 'Titular, parte en color', 'linea'],
        ['home', 'manifiesto.cierre', 'Titular, final', 'linea'],
        ['home', 'manifiesto.bloques.0.titulo', 'Primer bloque, título', 'linea'],
        ['home', 'manifiesto.bloques.0.texto', 'Primer bloque, texto', 'parrafo'],
        ['home', 'manifiesto.bloques.1.titulo', 'Segundo bloque, título', 'linea'],
        ['home', 'manifiesto.bloques.1.texto', 'Segundo bloque, texto', 'parrafo'],
        ['home', 'manifiesto.bloques.2.titulo', 'Tercer bloque, título', 'linea'],
        ['home', 'manifiesto.bloques.2.texto', 'Tercer bloque, texto', 'parrafo'],
        ['home', 'manifiesto.pie', 'Pie de la foto grande', 'linea'],
    ],
    'Sabai sabai' => [
        ['home', 'sabai.etiqueta', 'Etiqueta', 'linea'],
        ['home', 'sabai.titulo', 'Titular', 'linea'],
        ['home', 'sabai.parrafos.0', 'Primer párrafo', 'parrafo'],
        ['home', 'sabai.parrafos.1', 'Segundo párrafo', 'parrafo'],
        ['home', 'sabai.parrafos.2', 'Tercer párrafo', 'parrafo'],
        ['home', 'sabai.pregunta', 'Pregunta final', 'linea'],
        ['home', 'sabai.boton', 'Botón de WhatsApp', 'linea'],
    ],
    'Frase destacada' => [
        ['home', 'statement.antes', 'Frase, primera parte', 'linea'],
        ['home', 'statement.medio', 'Frase, parte central', 'linea'],
        ['home', 'statement.despues', 'Frase, parte final', 'linea'],
        ['home', 'statement.texto', 'Texto pequeño debajo', 'parrafo'],
    ],
    'El viaje (las etapas por zonas)' => [
        ['home', 'ruta.etiqueta', 'Etiqueta', 'linea'],
        ['home', 'ruta.titulo', 'Titular', 'linea'],
        ['home', 'video.titulo', 'Titular del vídeo', 'linea'],
        ['home', 'video.etiqueta', 'Etiqueta del vídeo', 'linea'],
    ],
    'La ruta en moto (los 6 días)' => [
        ['home', 'moto.etiqueta', 'Etiqueta', 'linea'],
        ['home', 'moto.titulo', 'Titular', 'linea'],
        ['home', 'moto.texto', 'Texto de entrada', 'parrafo'],
        ['home', 'moto.datos.0.valor', 'Dato 1, cifra', 'linea'],
        ['home', 'moto.datos.0.etiqueta', 'Dato 1, texto', 'linea'],
        ['home', 'moto.datos.1.valor', 'Dato 2, cifra', 'linea'],
        ['home', 'moto.datos.1.etiqueta', 'Dato 2, texto', 'linea'],
        ['home', 'moto.datos.2.valor', 'Dato 3, cifra', 'linea'],
        ['home', 'moto.datos.2.etiqueta', 'Dato 3, texto', 'linea'],
        ['home', 'moto.datos.3.valor', 'Dato 4, cifra', 'linea'],
        ['home', 'moto.datos.3.etiqueta', 'Dato 4, texto', 'linea'],
        ['home', 'moto.dias.0.titulo', 'Día 1, tramo', 'linea'],
        ['home', 'moto.dias.0.texto', 'Día 1, texto', 'parrafo'],
        ['home', 'moto.dias.1.titulo', 'Día 2, tramo', 'linea'],
        ['home', 'moto.dias.1.texto', 'Día 2, texto', 'parrafo'],
        ['home', 'moto.dias.2.titulo', 'Día 3, tramo', 'linea'],
        ['home', 'moto.dias.2.texto', 'Día 3, texto', 'parrafo'],
        ['home', 'moto.dias.3.titulo', 'Día 4, tramo', 'linea'],
        ['home', 'moto.dias.3.texto', 'Día 4, texto', 'parrafo'],
        ['home', 'moto.dias.4.titulo', 'Día 5, tramo', 'linea'],
        ['home', 'moto.dias.4.texto', 'Día 5, texto', 'parrafo'],
        ['home', 'moto.dias.5.titulo', 'Día 6, tramo', 'linea'],
        ['home', 'moto.dias.5.texto', 'Día 6, texto', 'parrafo'],
        ['home', 'moto.cierre', 'Frase de la última tarjeta', 'linea'],
        ['home', 'moto.cierreBoton', 'Botón de la última tarjeta', 'linea'],
    ],
    'Quién te acompaña' => [
        ['home', 'fundador.titulo', 'Titular', 'linea'],
        ['home', 'fundador.parrafos.0', 'Primer párrafo', 'parrafo'],
        ['home', 'fundador.parrafos.1', 'Segundo párrafo', 'parrafo'],
        ['home', 'fundador.parrafos.2', 'Tercer párrafo', 'parrafo'],
        ['home', 'fundador.boton', 'Botón', 'linea'],
        ['home', 'fundador.microcopy', 'Frase junto al botón', 'linea'],
        ['home', 'fundador.retratoPie', 'Pie del retrato (vacío para quitarlo)', 'linea'],
    ],
    'Qué incluye' => [
        ['home', 'incluye.titulo', 'Titular', 'linea'],
        ['home', 'incluye.microcopy', 'Texto', 'parrafo'],
        ['home', 'incluye.enlace', 'Texto del enlace a WhatsApp', 'linea'],
    ],
    'Cierre' => [
        ['home', 'cta.titulo', 'Titular', 'linea'],
        ['home', 'cta.subtitulo', 'Texto', 'parrafo'],
        ['home', 'cta.boton', 'Botón', 'linea'],
        ['home', 'cta.microcopy', 'Frase bajo el botón', 'linea'],
    ],
    'Carrusel de Instagram' => [
        ['home', 'instagram.etiqueta', 'Etiqueta', 'linea'],
        ['home', 'instagram.titulo', 'Titular', 'linea'],
        ['home', 'instagram.texto', 'Texto', 'parrafo'],
        ['home', 'instagram.boton', 'Botón', 'linea'],
        ['home', 'instagram.fotos.0.enlace', 'Foto 1, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.0.pie', 'Foto 1, texto al pasar el ratón', 'linea'],
        ['home', 'instagram.fotos.1.enlace', 'Foto 2, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.1.pie', 'Foto 2, texto al pasar el ratón', 'linea'],
        ['home', 'instagram.fotos.2.enlace', 'Foto 3, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.2.pie', 'Foto 3, texto al pasar el ratón', 'linea'],
        ['home', 'instagram.fotos.3.enlace', 'Foto 4, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.3.pie', 'Foto 4, texto al pasar el ratón', 'linea'],
        ['home', 'instagram.fotos.4.enlace', 'Foto 5, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.4.pie', 'Foto 5, texto al pasar el ratón', 'linea'],
        ['home', 'instagram.fotos.5.enlace', 'Foto 6, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.5.pie', 'Foto 6, texto al pasar el ratón', 'linea'],
        ['home', 'instagram.fotos.6.enlace', 'Foto 7, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.6.pie', 'Foto 7, texto al pasar el ratón', 'linea'],
        ['home', 'instagram.fotos.7.enlace', 'Foto 8, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.7.pie', 'Foto 8, texto al pasar el ratón', 'linea'],
        ['home', 'instagram.fotos.8.enlace', 'Foto 9, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.8.pie', 'Foto 9, texto al pasar el ratón', 'linea'],
        ['home', 'instagram.fotos.9.enlace', 'Foto 10, enlace a la publicación (vacío: va al perfil)', 'linea'],
        ['home', 'instagram.fotos.9.pie', 'Foto 10, texto al pasar el ratón', 'linea'],
    ],
    'Contacto y medición' => [
        ['sitio', 'whatsapp', 'WhatsApp (internacional, sin +)', 'linea'],
        ['sitio', 'email', 'Email', 'linea'],
        ['sitio', 'instagram', 'Instagram', 'linea'],
        ['sitio', 'instagramFeed', 'Feed automático de Instagram (enlace de Behold; vacío = carrusel manual)', 'linea'],
        ['sitio', 'formulario', 'Enlace del formulario de inscripción', 'linea'],
        ['sitio', 'analytics', 'Google Analytics (G-...)', 'linea'],
        ['sitio', 'verificacionGoogle', 'Verificación de Google Search Console', 'linea'],
    ],
];

$VIAJE = [
    ['fechas', 'Fechas (de momento no se muestran en la web)', 'linea'],
    ['duracion', 'Duración', 'linea'],
    ['plazas', 'Plazas totales', 'numero'],
    ['disponibles', 'Plazas libres', 'numero'],
    ['descripcion', 'Descripción', 'parrafo'],
];

$seccion = $_GET['s'] ?? 'textos';

/* ------------------------------------------------------------- guardar */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && dentro()) {
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // Si el archivo supera el límite del servidor, PHP vacía $_POST y parecía «sesión caducada».
        $aviso = ['mal', 'El archivo pesa más de lo que admite el servidor y no ha llegado. Prueba con uno más ligero.'];
    } elseif (!csrf_valido()) {
        $aviso = ['mal', 'La sesión ha caducado. Vuelve a intentarlo.'];
    } elseif (($_POST['accion'] ?? '') === 'textos') {
        $home = leer_json(HOME);
        $sitio = leer_json(SITIO);
        if (!$home || !$sitio) {
            $aviso = ['mal', 'No se ha podido leer el contenido. Revisa el token de GitHub.'];
        } else {
            $cambios = 0;
            foreach ($CAMPOS as $campos) {
                foreach ($campos as [$donde, $camino, , ]) {
                    $clave = $donde . '|' . $camino;
                    if (!isset($_POST['c'][$clave])) {
                        continue;
                    }
                    $nuevo = trim((string) $_POST['c'][$clave]);
                    if ($donde === 'home') {
                        if ($nuevo !== valor($home['datos'], $camino)) {
                            fijar($home['datos'], $camino, $nuevo);
                            $cambios++;
                        }
                    } elseif ($nuevo !== valor($sitio['datos'], $camino)) {
                        fijar($sitio['datos'], $camino, $nuevo);
                        $cambios++;
                    }
                }
            }
            if ($cambios === 0) {
                $aviso = ['ok', 'No había nada que cambiar.'];
            } else {
                [$ok1] = guardar_json(HOME, $home['datos'], $home['sha'], 'Panel: textos de la home');
                [$ok2, $err] = guardar_json(SITIO, $sitio['datos'], $sitio['sha'], 'Panel: datos de contacto');
                $aviso = $ok1 && $ok2
                    ? ['ok', 'Guardado. La web se actualiza en unos ' . MINUTOS_PUBLICACION . ' minutos.']
                    : ['mal', 'No se pudo guardar: ' . $err];
            }
        }
    } elseif (($_POST['accion'] ?? '') === 'viaje') {
        $ruta = 'content/salidas/' . basename((string) ($_POST['archivo'] ?? ''));
        $salida = leer_json($ruta);
        if (!$salida) {
            $aviso = ['mal', 'No se ha encontrado el viaje.'];
        } else {
            foreach ($VIAJE as [$clave, , $tipo]) {
                if (isset($_POST['v'][$clave])) {
                    $valor = trim((string) $_POST['v'][$clave]);
                    $salida['datos'][$clave] = $tipo === 'numero' ? (int) $valor : $valor;
                }
            }
            if (isset($_POST['v']['estado'])) {
                $salida['datos']['estado'] = (string) $_POST['v']['estado'];
            }
            [$ok, $err] = guardar_json($ruta, $salida['datos'], $salida['sha'], 'Panel: datos del viaje');
            $aviso = $ok
                ? ['ok', 'Guardado. La web se actualiza en unos ' . MINUTOS_PUBLICACION . ' minutos.']
                : ['mal', 'No se pudo guardar: ' . $err];
        }
    } elseif (($_POST['accion'] ?? '') === 'hueco') {
        $aviso = guardar_hueco();
    } elseif (($_POST['accion'] ?? '') === 'video') {
        $aviso = guardar_video();
    }
}

/**
 * Sitios de la web donde hay una foto, en el orden en que aparecen al bajar
 * por la página. Cada hueco apunta a un campo del contenido; cambiar la foto
 * de un hueco sube un archivo nuevo y solo cambia ese sitio, aunque la foto
 * anterior se usara también en otros.
 */
function huecos_fotos(array $viajes = [], array $etapas = []): array
{
    $lista = [
        ['Portada', 'Foto de fondo en ordenador', 'home', 'hero.poster'],
        ['Portada', 'Foto de fondo en móvil', 'home', 'hero.posterMovil'],
        ['¿Y si esta vez Tailandia fuera diferente?', 'Foto grande a todo lo ancho', 'home', 'manifiesto.imagen'],
        ['Sabai sabai', 'Foto principal', 'home', 'sabai.imagen'],
        ['Sabai sabai', 'Foto pequeña superpuesta', 'home', 'sabai.imagen2'],
    ];
    for ($i = 0; $i < 5; $i++) {
        $lista[] = ['El viaje', 'Etapa ' . ($i + 1) . (isset($etapas[$i]) && $etapas[$i] !== '' ? ' · ' . $etapas[$i] : ''), 'home', 'ruta.imagenes.' . $i];
    }
    $lista[] = ['El viaje', 'Portada del vídeo', 'home', 'video.poster'];
    for ($i = 0; $i < 6; $i++) {
        $lista[] = ['La ruta en moto', 'Día ' . ($i + 1), 'home', 'moto.dias.' . $i . '.imagen'];
    }
    for ($i = 0; $i < 10; $i++) {
        $lista[] = ['Carrusel de Instagram', 'Foto ' . ($i + 1), 'home', 'instagram.fotos.' . $i . '.imagen'];
    }
    $lista[] = ['Frase «No solo es viajar…»', 'Primera foto redonda', 'home', 'statement.imagen1'];
    $lista[] = ['Frase «No solo es viajar…»', 'Segunda foto redonda', 'home', 'statement.imagen2'];
    $lista[] = ['Quién te acompaña', 'Retrato de Dani', 'home', 'fundador.retrato'];
    $lista[] = ['Cierre', 'Foto de fondo del final', 'home', 'cta.imagen'];
    foreach ($viajes as $v) {
        $lista[] = ['Página del viaje', 'Foto de cabecera' . (count($viajes) > 1 ? ' · ' . str_replace('.json', '', $v) : ''), 'viaje:' . $v, 'imagen'];
    }
    return array_map(fn ($h) => ['grupo' => $h[0], 'etiqueta' => $h[1], 'fuente' => $h[2], 'camino' => $h[3], 'id' => $h[2] . '|' . $h[3]], $lista);
}

/** Archivo del repositorio donde vive el contenido de un hueco. */
function ruta_fuente(string $fuente): string
{
    return $fuente === 'home' ? HOME : 'content/salidas/' . basename(substr($fuente, strlen('viaje:')));
}

/** Nombres de los archivos de viajes (content/salidas/*.json). */
function nombres_viajes(): array
{
    return array_values(array_map(
        fn ($f) => (string) $f['name'],
        array_filter(listar('content/salidas'), fn ($f) => str_ends_with($f['name'] ?? '', '.json'))
    ));
}

/** Título y descripción enviados desde un formulario, ya limpios. */
function textos_enviados(): array
{
    return [
        'titulo' => trim((string) ($_POST['titulo'] ?? '')),
        'descripcion' => trim((string) preg_replace('/\s+/', ' ', (string) ($_POST['descripcion'] ?? ''))),
    ];
}

/** Comprueba la foto subida y devuelve [extensión, error]. */
function validar_foto(): array
{
    if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        return ['', 'La foto no ha llegado entera. ¿Pesa más de 8 MB?'];
    }
    if ((int) $_FILES['archivo']['size'] > 8 * 1024 * 1024) {
        return ['', 'La foto pesa ' . round($_FILES['archivo']['size'] / 1048576, 1) . ' MB y el máximo son 8 MB.'];
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['archivo']['tmp_name']);
    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($permitidos[$mime])) {
        return ['', 'Solo se admiten imágenes JPG, PNG o WEBP.'];
    }
    return [$permitidos[$mime], ''];
}

/** Cambia la foto de un sitio de la web y/o su título y descripción. */
function guardar_hueco(): array
{
    $id = (string) ($_POST['hueco'] ?? '');
    $hueco = null;
    foreach (huecos_fotos(nombres_viajes()) as $h) {
        if ($h['id'] === $id) {
            $hueco = $h;
        }
    }
    if (!$hueco) {
        return ['mal', 'No se ha reconocido ese sitio de la web. Recarga la página.'];
    }
    $ruta = ruta_fuente($hueco['fuente']);
    $doc = leer_json($ruta);
    if (!$doc) {
        return ['mal', 'No se ha podido leer el contenido. Revisa el token de GitHub.'];
    }
    $archivo = basename(valor($doc['datos'], $hueco['camino']));
    $hecho = [];

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        [$ext, $error] = validar_foto();
        if ($error !== '') {
            return ['mal', $error];
        }
        $base = (string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo((string) $_FILES['archivo']['name'], PATHINFO_FILENAME)));
        $base = trim(substr($base, 0, 40), '-');
        $nuevo = ($base !== '' ? $base : 'foto') . '-' . date('ymdHis') . '.' . $ext;
        [$ok, $err] = guardar_archivo(IMGS . '/' . $nuevo, (string) file_get_contents($_FILES['archivo']['tmp_name']), null, 'Panel: foto nueva ' . $nuevo);
        if (!$ok) {
            return ['mal', 'No se pudo subir la foto: ' . $err];
        }
        $prefijo = $hueco['fuente'] === 'home' ? '../src/assets/img/' : '../../src/assets/img/';
        fijar($doc['datos'], $hueco['camino'], $prefijo . $nuevo);
        [$ok, $err] = guardar_json($ruta, $doc['datos'], $doc['sha'], 'Panel: foto de ' . $hueco['grupo'] . ' · ' . $hueco['etiqueta']);
        if (!$ok) {
            return ['mal', 'La foto se ha subido pero no se ha podido colocar: ' . $err];
        }
        $archivo = $nuevo;
        $hecho[] = 'Foto cambiada.';
    }

    $textos = textos_enviados();
    $tf = leer_json(TEXTOS_FOTOS);
    $datos = $tf['datos'] ?? [];
    if ($archivo !== '' && ($datos[$archivo] ?? null) !== $textos) {
        $datos[$archivo] = $textos;
        ksort($datos);
        [$ok, $err] = guardar_json(TEXTOS_FOTOS, $datos, $tf['sha'] ?? null, 'Panel: textos de la foto ' . $archivo);
        if (!$ok) {
            return ['mal', implode(' ', $hecho) . ' No se han podido guardar los textos: ' . $err];
        }
        $hecho[] = 'Textos guardados.';
    }
    return $hecho
        ? ['ok', implode(' ', $hecho) . ' La web se actualiza en unos ' . MINUTOS_PUBLICACION . ' minutos.']
        : ['ok', 'No había nada que cambiar.'];
}

/** Sitios de la web con vídeo, en orden: [donde, grupo, etiqueta, prefijo en home.json, campo de la URL]. */
function huecos_videos(): array
{
    return [
        ['hero', 'Portada', 'Vídeo de fondo de la portada', 'hero.', 'hero.videoMp4'],
        ['banda', 'El viaje', 'Vídeo de la tarjeta con botón de play', 'video.', 'video.mp4'],
    ];
}

/** Sube un vídeo (opcional) y guarda su título, descripción y, en portada, el sonido. */
function guardar_video(): array
{
    $hueco = null;
    foreach (huecos_videos() as $h) {
        if ($h[0] === ($_POST['donde'] ?? '')) {
            $hueco = $h;
        }
    }
    if (!$hueco) {
        return ['mal', 'No se ha reconocido ese vídeo. Recarga la página.'];
    }
    [$donde, $grupo, , $pre, $campoUrl] = $hueco;
    $home = leer_json(HOME);
    if (!$home) {
        return ['mal', 'No se ha podido leer el contenido. Revisa el token de GitHub.'];
    }
    $antes = $home['datos'];
    $subido = '';

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            return ['mal', 'El vídeo no ha llegado entero. ¿Pesa más de 300 MB?'];
        }
        $tmp = $_FILES['archivo']['tmp_name'];
        $peso = (int) $_FILES['archivo']['size'];
        if ($peso > 300 * 1024 * 1024) {
            return ['mal', 'El vídeo pesa ' . round($peso / 1048576, 1) . ' MB y el máximo son 300 MB.'];
        }
        if ((string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp) !== 'video/mp4') {
            return ['mal', 'El vídeo debe ser un MP4.'];
        }
        // Los vídeos se guardan en el propio hosting, no en el repositorio: pesan
        // demasiado para la API de GitHub y no tiene sentido versionarlos.
        $nombre = preg_replace('/[^a-z0-9._-]/', '-', strtolower((string) $_FILES['archivo']['name'])) ?: 'video.mp4';
        if (!str_ends_with($nombre, '.mp4')) {
            $nombre .= '.mp4';
        }
        if (!@move_uploaded_file($tmp, carpeta_videos() . '/' . $nombre)) {
            return ['mal', 'No se pudo guardar el vídeo en el servidor. Comprueba los permisos de la carpeta video.'];
        }
        fijar($home['datos'], $campoUrl, URL_VIDEOS . '/' . $nombre);
        fijar($home['datos'], $pre . 'videoFecha', date('Y-m-d'));
        $subido = 'Vídeo subido (' . round($peso / 1048576, 1) . ' MB). ';
    }

    $textos = textos_enviados();
    foreach (['videoTitulo' => $textos['titulo'], 'videoDescripcion' => $textos['descripcion']] as $campo => $v) {
        if (valor($home['datos'], $pre . $campo) !== $v) {
            fijar($home['datos'], $pre . $campo, $v);
        }
    }
    if ($donde === 'hero' && (valor($home['datos'], 'hero.videoSonido') === '1') !== isset($_POST['sonido'])) {
        fijar($home['datos'], 'hero.videoSonido', isset($_POST['sonido']));
    }
    if ($home['datos'] === $antes) {
        return ['ok', 'No había nada que cambiar.'];
    }
    [$ok, $err] = guardar_json(HOME, $home['datos'], $home['sha'], 'Panel: vídeo de ' . $grupo);
    return $ok
        ? ['ok', $subido . 'Guardado. La web se actualiza en unos ' . MINUTOS_PUBLICACION . ' minutos.']
        : ['mal', $subido . 'No se pudo guardar: ' . $err];
}

/* --------------------------------------------------------------- datos */
$home = leer_json(HOME);
$sitio = leer_json(SITIO);
$sinConexion = !$home || !$sitio;
$textosFotos = $sinConexion ? [] : ((leer_json(TEXTOS_FOTOS) ?? [])['datos'] ?? []);
$salidas = $sinConexion ? [] : array_values(array_filter(listar('content/salidas'), fn ($f) => str_ends_with($f['name'] ?? '', '.json')));

require __DIR__ . '/vista.php';

/* ------------------------------------------------------------ pantalla */
function pintar_entrada(?array $aviso): void
{
    $titulo = 'Panel · Explora Siam';
    require __DIR__ . '/entrada.php';
}
