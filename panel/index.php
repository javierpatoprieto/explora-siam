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

// Para saber desde fuera qué versión del panel hay subida al hosting (y que el
// navegador no sirva una página vieja con los campos de antes).
header('X-Panel-Actualizado: ' . gmdate('Y-m-d H:i', (int) @filemtime(__FILE__)) . ' UTC');
header('Cache-Control: no-store, must-revalidate');

const HOME = 'content/home.json';
const SITIO = 'content/site.json';
const IMGS = 'src/assets/img';
const TEXTOS_FOTOS = 'content/fotos.json';
const FAQS = 'content/faqs';
const SECCIONES = 'content/secciones.json';
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
        ['home', 'hero.botonSecundario', 'Segundo botón (lleva a la ruta)', 'linea'],
    ],
    'Cifras de la portada' => [
        ['home', 'cifrasTexto.titulo', 'Titular junto a las cifras', 'linea'],
        ['home', 'cifrasTexto.texto', 'Texto junto a las cifras', 'parrafo'],
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
        ['home', 'sabai.pie', 'Pie de la foto principal', 'linea'],
        ['home', 'sabai.pie2', 'Pie de la foto pequeña', 'linea'],
    ],
    'Frase destacada' => [
        ['home', 'statement.frase', 'Frase', 'parrafo'],
        ['home', 'statement.texto', 'Texto pequeño debajo', 'linea'],
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
    'Por qué conmigo' => [
        ['home', 'ventajas.titulo', 'Titular', 'linea'],
        ['home', 'ventajas.texto', 'Texto de entrada', 'parrafo'],
    ],
    'Quién te acompaña' => [
        ['home', 'fundador.etiqueta', 'Etiqueta', 'linea'],
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
    'Preguntas frecuentes' => [
        ['home', 'faq.titulo', 'Titular', 'linea'],
        ['home', 'faq.intro', 'Texto de entrada', 'parrafo'],
        ['home', 'faq.enlace', 'Texto del enlace a WhatsApp', 'linea'],
        ['home', 'faq.verTodas', 'Texto del enlace a todas las preguntas', 'linea'],
    ],
    'Cierre' => [
        ['home', 'cta.titulo', 'Titular', 'linea'],
        ['home', 'cta.subtitulo', 'Texto', 'parrafo'],
        ['home', 'cta.boton', 'Botón', 'linea'],
        ['home', 'cta.microcopy', 'Frase bajo el botón', 'linea'],
    ],
    'Pie de página' => [
        ['home', 'footer.frase', 'Frase del pie', 'linea'],
    ],
    'Página de cada viaje' => [
        ['sitio', 'paginaViaje.ultimas', 'Aviso de «últimas plazas»', 'linea'],
        ['sitio', 'paginaViaje.completa', 'Aviso de «grupo completo»', 'linea'],
        ['sitio', 'paginaViaje.grupo', 'Línea bajo el título ({plazas} se cambia por el número)', 'linea'],
        ['sitio', 'paginaViaje.diaADia', 'Titular del itinerario', 'linea'],
        ['sitio', 'paginaViaje.etapa', 'Palabra para cada etapa', 'linea'],
        ['sitio', 'paginaViaje.sinItinerario', 'Texto cuando aún no hay itinerario', 'parrafo'],
        ['sitio', 'paginaViaje.botonQuiero', 'Botón principal', 'linea'],
        ['sitio', 'paginaViaje.botonAvisame', 'Botón cuando el grupo está completo', 'linea'],
        ['sitio', 'paginaViaje.microcopy', 'Frase bajo el botón', 'linea'],
        ['sitio', 'paginaViaje.botonFormulario', 'Botón del formulario', 'linea'],
        ['sitio', 'paginaViaje.enlaceDossier', 'Enlace del dossier', 'linea'],
    ],
    'Colores de la web' => [
        ['sitio', 'estilo.acento', 'Color principal (botones y detalles), en formato #rrggbb', 'linea'],
        ['sitio', 'estilo.acentoSuave', 'Color principal suave (fondos)', 'linea'],
        ['sitio', 'estilo.tinta', 'Color del texto y los fondos oscuros', 'linea'],
    ],
    'Contacto y medición' => [
        ['sitio', 'whatsapp', 'WhatsApp (internacional, sin +)', 'linea'],
        ['sitio', 'email', 'Email', 'linea'],
        ['sitio', 'instagram', 'Instagram', 'linea'],
        ['sitio', 'formulario', 'Enlace del formulario de inscripción', 'linea'],
        ['sitio', 'analytics', 'Google Analytics (G-...)', 'linea'],
        ['sitio', 'verificacionGoogle', 'Verificación de Google Search Console', 'linea'],
    ],
];

/* --------------------------------------------- el panel se actualiza solo */
// El panel vive en el hosting, no en Vercel, así que no se publica con la web.
// Para no depender de subirlo a mano, se trae sus propios archivos de GitHub.
// Nunca toca config.php (ahí están la contraseña y el token) y guarda una copia
// de lo que sustituye, por si hubiera que volver atrás.

const ARCHIVOS_PANEL = ['index.php', 'lib.php', 'vista.php', 'entrada.php', 'estilo.php'];

/** Descarga un archivo del repositorio tal cual (sin pasar por base64). */
function bajar_del_repo(string $ruta): ?string
{
    [$codigo, $datos] = gh('GET', repo() . '/contents/' . rawurlencode_ruta($ruta) . '?ref=' . GITHUB_BRANCH);
    if ($codigo !== 200 || !isset($datos['content'])) {
        return null;
    }
    $contenido = base64_decode(str_replace("\n", '', (string) $datos['content']));
    return $contenido === '' ? null : $contenido;
}

/**
 * Deja un archivo listo para comparar: sin espacios de más y sin la etiqueta de
 * codificación, que el gestor de archivos de cPanel recoloca al guardar y haría
 * creer que el panel está desfasado cuando no lo está.
 */
function igualar(string $texto): string
{
    $texto = str_replace('<meta charset="utf-8">', '', $texto);
    return (string) preg_replace('/\s+/', '', $texto);
}

/** Qué archivos del panel son distintos a los de GitHub. */
function panel_pendiente(): array
{
    $distintos = [];
    foreach (ARCHIVOS_PANEL as $archivo) {
        $remoto = bajar_del_repo('panel/' . $archivo);
        if ($remoto === null) {
            continue;
        }
        $local = @file_get_contents(__DIR__ . '/' . $archivo);
        if ($local === false || igualar($local) !== igualar($remoto)) {
            $distintos[$archivo] = $remoto;
        }
    }
    return $distintos;
}

/**
 * Sustituye los archivos del panel por los de GitHub.
 * Antes comprueba que lo descargado es PHP válido: si no, no toca nada.
 */
function actualizar_panel(): array
{
    $distintos = panel_pendiente();
    if (!$distintos) {
        return ['ok', 'El panel ya está al día.'];
    }
    foreach ($distintos as $archivo => $contenido) {
        // Dos redes: que no venga cortado y que PHP sepa leerlo. Si algo falla,
        // no se toca ni un archivo, para no dejar el panel a medias.
        if (strlen($contenido) < 200) {
            return ['mal', 'La descarga de ' . $archivo . ' viene demasiado corta: no se ha cambiado nada.'];
        }
        try {
            token_get_all($contenido, TOKEN_PARSE);
        } catch (\Throwable $e) {
            return ['mal', 'El archivo ' . $archivo . ' venía con errores: no se ha cambiado nada.'];
        }
    }
    $copias = __DIR__ . '/.copias/' . date('ymd-His');
    if (!@mkdir($copias, 0755, true) && !is_dir($copias)) {
        return ['mal', 'No se ha podido crear la carpeta de copias. Revisa los permisos.'];
    }
    $hechos = [];
    foreach ($distintos as $archivo => $contenido) {
        $destino = __DIR__ . '/' . $archivo;
        if (is_file($destino)) {
            @copy($destino, $copias . '/' . $archivo);
        }
        $temporal = $destino . '.nuevo';
        if (@file_put_contents($temporal, $contenido) === false || !@rename($temporal, $destino)) {
            @unlink($temporal);
            return ['mal', 'No se ha podido escribir ' . $archivo . '. Copia de seguridad en ' . basename($copias) . '.'];
        }
        $hechos[] = $archivo;
    }
    return ['ok', 'Panel actualizado (' . implode(', ', $hechos) . '). Recarga la página.'];
}

/* ------------------------------------------------- listas y secciones */
// Además de cambiar textos, Dani puede añadir y quitar elementos de las listas
// (cifras, puntos de «qué incluye», días de la ruta…) y decidir qué secciones
// se ven y en qué orden.

/** Listas que se pueden alargar o acortar desde el panel. */
function listas_editables(): array
{
    return [
        ['hechos', 'Datos rápidos de la portada', 'texto'],
        ['hero.cinta', 'Cinta de sitios de la portada', 'texto'],
        ['cifras', 'Cifras', ['valor' => '', 'unidad' => '', 'etiqueta' => '']],
        ['manifiesto.bloques', 'Bloques del texto', ['titulo' => '', 'texto' => '']],
        ['ventajas.items', 'Puntos de «Por qué conmigo»', ['icono' => 'guia', 'titulo' => '', 'texto' => '']],
        ['incluye.incluido', 'Lo que incluye', 'texto'],
        ['incluye.noIncluido', 'Lo que no incluye', 'texto'],
        ['fundador.parrafos', 'Párrafos de la carta', 'texto'],
        ['fundador.datos', 'Datos junto al retrato', 'texto'],
        ['moto.datos', 'Cifras de la ruta en moto', ['valor' => '', 'etiqueta' => '']],
        ['moto.dias', 'Días de la ruta en moto', ['titulo' => '', 'texto' => '', 'imagen' => '']],
    ];
}

/** La lista tal y como está guardada. */
function lista_actual(array $datos, string $camino): array
{
    foreach (explode('.', $camino) as $parte) {
        if (!is_array($datos) || !array_key_exists($parte, $datos)) {
            return [];
        }
        $datos = $datos[$parte];
    }
    return is_array($datos) ? $datos : [];
}

/**
 * Añade, quita o mueve un elemento de una lista de la home.
 * Devuelve [cambió, mensaje de error].
 */
function tocar_lista(array &$home, string $orden, string $camino, int $indice): array
{
    $molde = null;
    $nombre = $camino;
    foreach (listas_editables() as [$ruta, $etiqueta, $tipo]) {
        if ($ruta === $camino) {
            $molde = $tipo;
            $nombre = $etiqueta;
        }
    }
    if ($molde === null) {
        return [false, 'Esa lista no se puede cambiar desde aquí.'];
    }
    $lista = array_values(lista_actual($home, $camino));

    if ($orden === 'add') {
        if ($molde === 'texto') {
            $lista[] = '';
        } else {
            $nuevo = $molde;
            // Las fotos se heredan del último, para que no quede ningún hueco sin imagen.
            $ultimo = $lista ? (array) end($lista) : [];
            foreach ($nuevo as $k => $v) {
                if ($v === '' && str_contains(strtolower($k), 'imagen') && isset($ultimo[$k])) {
                    $nuevo[$k] = $ultimo[$k];
                }
            }
            $lista[] = $nuevo;
        }
        fijar($home, $camino, $lista);
        return [true, ''];
    }

    if (!array_key_exists($indice, $lista)) {
        return [false, 'Ese elemento ya no está.'];
    }

    if ($orden === 'del') {
        if (count($lista) <= 1) {
            return [false, 'No se puede quedar vacía «' . $nombre . '».'];
        }
        array_splice($lista, $indice, 1);
        fijar($home, $camino, $lista);
        return [true, ''];
    }

    $destino = $orden === 'sube' ? $indice - 1 : $indice + 1;
    if ($destino < 0 || $destino >= count($lista)) {
        return [false, ''];
    }
    [$lista[$indice], $lista[$destino]] = [$lista[$destino], $lista[$indice]];
    fijar($home, $camino, $lista);
    return [true, ''];
}

/** Nombre corto de cada elemento de una lista, para el desplegable. */
function resumen_elemento($elemento, int $i): string
{
    $texto = is_array($elemento)
        ? (string) ($elemento['titulo'] ?? $elemento['valor'] ?? $elemento['texto'] ?? '')
        : (string) $elemento;
    $texto = trim((string) preg_replace('/\s+/', ' ', $texto));
    if ($texto === '') {
        return ($i + 1) . '. (vacío)';
    }
    return ($i + 1) . '. ' . (mb_strlen($texto) > 40 ? mb_substr($texto, 0, 38) . '…' : $texto);
}

/** Secciones de la home, con el nombre que ve Dani. */
function nombres_secciones(): array
{
    return [
        'manifiesto' => '¿Y si esta vez Tailandia fuera diferente?',
        'sabai' => 'Sabai sabai',
        'cifras' => 'Cifras',
        'viaje' => 'El viaje (etapas por zonas)',
        'moto' => 'La ruta en moto',
        'statement' => 'Frase destacada',
        'ventajas' => 'Por qué conmigo',
        'fundador' => 'Quién te acompaña',
        'incluye' => 'Qué incluye',
        'testimonios' => 'Testimonios',
        'faq' => 'Preguntas frecuentes',
        'cta' => 'Cierre',
    ];
}

/** Orden guardado, completado con las secciones que falten. */
function orden_secciones(?array $doc): array
{
    $orden = [];
    foreach ((($doc['datos'] ?? [])['orden'] ?? []) as $s) {
        $id = (string) ($s['id'] ?? '');
        if (isset(nombres_secciones()[$id])) {
            $orden[$id] = ['id' => $id, 'mostrar' => (bool) ($s['mostrar'] ?? true)];
        }
    }
    foreach (nombres_secciones() as $id => $nombre) {
        $orden[$id] ??= ['id' => $id, 'mostrar' => true];
    }
    return array_values($orden);
}

/** Guarda la sección de secciones: qué se ve y en qué orden. */
function guardar_secciones(): array
{
    $doc = leer_json(SECCIONES);
    $orden = orden_secciones($doc);
    $mover = (string) ($_POST['sube'] ?? $_POST['baja'] ?? '');
    if ($mover !== '') {
        $arriba = isset($_POST['sube']);
        foreach ($orden as $i => $s) {
            if ($s['id'] !== $mover) {
                continue;
            }
            $destino = $arriba ? $i - 1 : $i + 1;
            if ($destino < 0 || $destino >= count($orden)) {
                return ['ok', 'Ya estaba en el extremo.'];
            }
            [$orden[$i], $orden[$destino]] = [$orden[$destino], $orden[$i]];
            break;
        }
    } else {
        foreach ($orden as $i => $s) {
            $orden[$i]['mostrar'] = isset($_POST['mostrar'][$s['id']]);
        }
    }
    [$ok, $err] = guardar_json(SECCIONES, ['orden' => $orden], $doc['sha'] ?? null, 'Panel: secciones de la web');
    return $ok
        ? ['ok', 'Guardado. La web se actualiza en unos ' . MINUTOS_PUBLICACION . ' minutos.']
        : ['mal', 'No se pudo guardar: ' . $err];
}

/* ------------------------------------------------------------- preguntas */
// Cada pregunta es un archivo suelto en content/faqs. Aquí se pueden cambiar,
// añadir y borrar. Solo se guardan las que cambian, para no llenar el historial.

/** Lee todas las preguntas con su sha, ordenadas como salen en la web. */
function leer_preguntas(): array
{
    $lista = [];
    foreach (listar(FAQS) as $f) {
        $nombre = (string) ($f['name'] ?? '');
        if (!str_ends_with($nombre, '.json')) {
            continue;
        }
        $doc = leer_json(FAQS . '/' . $nombre);
        if ($doc) {
            $lista[] = ['archivo' => $nombre, 'datos' => $doc['datos'], 'sha' => $doc['sha']];
        }
    }
    usort($lista, fn ($a, $b) => ((int) ($a['datos']['orden'] ?? 99)) <=> ((int) ($b['datos']['orden'] ?? 99)));
    return $lista;
}

/** Nombre de archivo a partir de la pregunta: «¿Qué carnet necesito?» → carnet.json */
function nombre_archivo_pregunta(string $pregunta, array $ocupados): string
{
    $base = strtolower(strtr($pregunta, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
    ]));
    $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $base), '-');
    $base = $base === '' ? 'pregunta' : substr($base, 0, 40);
    $nombre = $base . '.json';
    $n = 2;
    while (in_array($nombre, $ocupados, true)) {
        $nombre = $base . '-' . $n++ . '.json';
    }
    return $nombre;
}

/** Guarda los cambios de la sección de preguntas. Devuelve el aviso. */
function guardar_preguntas(): array
{
    $preguntas = leer_preguntas();
    if (!$preguntas && listar(FAQS)) {
        return ['mal', 'No se han podido leer las preguntas. Revisa el token de GitHub.'];
    }

    // Borrar
    $borrar = basename((string) ($_POST['borrar'] ?? ''));
    if ($borrar !== '') {
        foreach ($preguntas as $p) {
            if ($p['archivo'] === $borrar) {
                [$codigo, $datos] = gh('DELETE', repo() . '/contents/' . rawurlencode_ruta(FAQS . '/' . $borrar), [
                    'message' => 'Panel: quitar la pregunta ' . $borrar,
                    'sha' => $p['sha'],
                    'branch' => GITHUB_BRANCH,
                ]);
                return $codigo === 200
                    ? ['ok', 'Pregunta borrada. La web se actualiza en unos ' . MINUTOS_PUBLICACION . ' minutos.']
                    : ['mal', 'No se pudo borrar: ' . mensaje_github($codigo, $datos)];
            }
        }
        return ['mal', 'Esa pregunta ya no está.'];
    }

    $cambios = 0;
    $fallo = '';

    // Cambios en las que ya existen
    foreach ($preguntas as $p) {
        $enviado = $_POST['p'][$p['archivo']] ?? null;
        if (!is_array($enviado)) {
            continue;
        }
        $antes = $p['datos'];
        $p['datos']['pregunta'] = trim((string) ($enviado['pregunta'] ?? ''));
        $p['datos']['respuesta'] = trim((string) ($enviado['respuesta'] ?? ''));
        $p['datos']['orden'] = (int) ($enviado['orden'] ?? 99);
        $p['datos']['enHome'] = isset($enviado['enHome']);
        if ($p['datos'] === $antes) {
            continue;
        }
        if ($p['datos']['pregunta'] === '' || $p['datos']['respuesta'] === '') {
            $fallo = 'Una pregunta se ha quedado sin texto: no se ha guardado.';
            continue;
        }
        [$ok, $err] = guardar_json(FAQS . '/' . $p['archivo'], $p['datos'], $p['sha'], 'Panel: pregunta ' . $p['datos']['pregunta']);
        $ok ? $cambios++ : $fallo = $err;
    }

    // Pregunta nueva
    $nueva = trim((string) ($_POST['nueva']['pregunta'] ?? ''));
    $respuesta = trim((string) ($_POST['nueva']['respuesta'] ?? ''));
    if ($nueva !== '' && $respuesta !== '') {
        $archivo = nombre_archivo_pregunta($nueva, array_column($preguntas, 'archivo'));
        $orden = 1 + max([0] + array_map(fn ($p) => (int) ($p['datos']['orden'] ?? 0), $preguntas));
        [$ok, $err] = guardar_json(FAQS . '/' . $archivo, [
            'pregunta' => $nueva,
            'respuesta' => $respuesta,
            'enHome' => isset($_POST['nueva']['enHome']),
            'orden' => $orden,
        ], null, 'Panel: pregunta nueva ' . $nueva);
        $ok ? $cambios++ : $fallo = $err;
    } elseif ($nueva !== '' || $respuesta !== '') {
        $fallo = 'Para añadir una pregunta hacen falta la pregunta y la respuesta.';
    }

    if ($fallo !== '') {
        return ['mal', ($cambios > 0 ? 'Se guardaron ' . $cambios . ', pero ' : '') . $fallo];
    }
    return $cambios === 0
        ? ['ok', 'No había nada que cambiar.']
        : ['ok', 'Guardado. La web se actualiza en unos ' . MINUTOS_PUBLICACION . ' minutos.'];
}

/* --------------------------------------------- campos automáticos de texto */
// El panel enseña primero los campos con nombre bonito de $CAMPOS. Para que no
// se quede ningún texto sin poder tocar, estas funciones recorren el contenido
// y añaden todo lo que falte, agrupado por sección.

/** Secciones del contenido que la web ya no usa: no se enseñan. */
const SECCIONES_MUERTAS = ['galeria', 'dia', 'precio', 'salidas', 'instagram'];

/** Campos que no son texto que Dani deba escribir (fotos, rutas, medición). */
function camino_tecnico(string $camino): bool
{
    return (bool) preg_match(
        '#(^|\.)(imagen|imagenes|imagen1|imagen2|poster|posterMovil|retrato|archivo|fondo|mp4|slug|foco|focos|icono|analytics|verificacionGoogle|videoFecha|videoMp4|videoWebm|indexar|autor|licencia)(\.|$)#',
        $camino
    );
}

/** Todos los textos de un documento, como 'camino.con.puntos' => texto. */
function hojas_de_texto(array $datos, string $prefijo = ''): array
{
    $hojas = [];
    foreach ($datos as $clave => $valor) {
        $camino = $prefijo === '' ? (string) $clave : $prefijo . '.' . $clave;
        if (is_array($valor)) {
            $hojas += hojas_de_texto($valor, $camino);
        } elseif (is_string($valor)) {
            // Ojo: también los vacíos. Si no, al añadir un hueco nuevo no salía dónde escribirlo.
            $hojas[$camino] = $valor;
        }
    }
    return $hojas;
}

/** Nombre en cristiano de la sección a la que pertenece un campo. */
function nombre_seccion(string $donde, string $camino): string
{
    if ($donde === 'sitio') {
        return str_starts_with($camino, 'plantillas.') ? 'Mensajes de WhatsApp' : 'Contacto y medición · más campos';
    }
    $nombres = [
        'hero' => 'Portada',
        'hechos' => 'Datos rápidos de la portada',
        'manifiesto' => '¿Y si esta vez Tailandia fuera diferente?',
        'sabai' => 'Sabai sabai',
        'cifras' => 'Cifras',
        'cifrasTexto' => 'Cifras',
        'ruta' => 'El viaje',
        'moto' => 'La ruta en moto',
        'video' => 'Vídeo del viaje',
        'statement' => 'Frase destacada',
        'ventajas' => 'Por qué conmigo',
        'fundador' => 'Quién te acompaña',
        'incluye' => 'Qué incluye',
        'testimonios' => 'Testimonios',
        'faq' => 'Bloque de preguntas',
        'cta' => 'Cierre',
        'footer' => 'Pie de página',
    ];
    $raiz = explode('.', $camino)[0];
    return ($nombres[$raiz] ?? ucfirst($raiz)) . ' · más textos';
}

/** Nombre en cristiano de un campo a partir de su camino. */
function nombre_campo(string $camino): string
{
    $palabras = [
        'titulo' => 'Titular',
        'subtitulo' => 'Subtítulo',
        'texto' => 'Texto',
        'etiqueta' => 'Etiqueta',
        'boton' => 'Botón',
        'botonPrimario' => 'Botón principal',
        'botonSecundario' => 'Botón secundario',
        'microcopy' => 'Frase pequeña',
        'intro' => 'Entradilla',
        'nota' => 'Nota',
        'pie' => 'Pie',
        'cierre' => 'Frase final',
        'cierreBoton' => 'Botón final',
        'valor' => 'Cifra',
        'unidad' => 'Unidad',
        'badge' => 'Etiqueta pequeña',
        'destacado' => 'Final del titular (en color)',
        'frase' => 'Frase',
        'enlace' => 'Texto del enlace',
        'verTodas' => 'Texto de «ver todas»',
        'antes' => 'Frase, primera parte',
        'medio' => 'Frase, parte central',
        'despues' => 'Frase, parte final',
        'pregunta' => 'Pregunta',
        'respuesta' => 'Respuesta',
        'retratoPie' => 'Pie del retrato',
        'videoTitulo' => 'Título del vídeo',
        'videoDescripcion' => 'Descripción del vídeo',
        'hora' => 'Hora',
        'cinta' => 'Cinta de sitios',
        'parrafos' => 'Párrafo',
        'bloques' => 'Bloque',
        'items' => 'Punto',
        'dias' => 'Día',
        'datos' => 'Dato',
        'paradas' => 'Parada',
        'pies' => 'Pie de foto',
        'highlights' => 'Punto fuerte',
    ];
    $partes = explode('.', $camino);
    array_shift($partes); // la sección ya da nombre al grupo
    $trozos = [];
    foreach ($partes as $p) {
        if (ctype_digit($p)) {
            $ultimo = count($trozos) - 1;
            $trozos[$ultimo] = ($trozos[$ultimo] ?? 'Elemento') . ' ' . ((int) $p + 1);
            continue;
        }
        $trozos[] = $palabras[$p] ?? ucfirst((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $p));
    }
    return $trozos ? implode(' · ', $trozos) : 'Texto';
}

/** Grupos de campos que faltan por cubrir, para añadirlos detrás de $CAMPOS. */
function campos_auto(array $home, array $sitio, array $CAMPOS): array
{
    $ya = [];
    foreach ($CAMPOS as $campos) {
        foreach ($campos as [$donde, $camino, , ]) {
            $ya[$donde . '|' . $camino] = true;
        }
    }
    $extra = [];
    foreach ([['home', $home], ['sitio', $sitio]] as [$donde, $datos]) {
        foreach (hojas_de_texto($datos) as $camino => $texto) {
            if (isset($ya[$donde . '|' . $camino]) || camino_tecnico($camino)) {
                continue;
            }
            if ($donde === 'home' && in_array(explode('.', $camino)[0], SECCIONES_MUERTAS, true)) {
                continue;
            }
            $largo = mb_strlen($texto) > 80 || str_contains($texto, "\n");
            $extra[nombre_seccion($donde, $camino)][] = [$donde, $camino, nombre_campo($camino), $largo ? 'parrafo' : 'linea'];
        }
    }
    return $extra;
}

/** Campos del viaje que no están en $VIAJE, para que se pueda editar todo. */
function campos_viaje(array $datos, array $VIAJE): array
{
    $ya = array_column($VIAJE, 0);
    $ya[] = 'estado';
    $extra = [];
    foreach (hojas_de_texto($datos) as $camino => $texto) {
        if (in_array($camino, $ya, true) || camino_tecnico($camino)) {
            continue;
        }
        $largo = mb_strlen($texto) > 80 || str_contains($texto, "\n");
        $extra[] = [$camino, nombre_campo('viaje.' . $camino), $largo ? 'parrafo' : 'linea'];
    }
    return $extra;
}

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
            $CAMPOS = array_merge($CAMPOS, campos_auto($home['datos'], $sitio['datos'], $CAMPOS));
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
            // Añadir, quitar o mover un elemento de una lista, si se ha pulsado uno de esos botones.
            $ordenLista = '';
            $caminoLista = '';
            foreach (['add', 'del', 'sube', 'baja'] as $op) {
                if (isset($_POST['lista_' . $op]) && $_POST['lista_' . $op] !== '') {
                    $ordenLista = $op;
                    $caminoLista = (string) $_POST['lista_' . $op];
                }
            }
            $errorLista = '';
            if ($ordenLista !== '') {
                $indice = (int) ($_POST['lista_idx'][$caminoLista] ?? 0);
                [$hecho, $errorLista] = tocar_lista($home['datos'], $ordenLista, $caminoLista, $indice);
                if ($hecho) {
                    $cambios++;
                }
            }
            if ($cambios === 0) {
                $aviso = ['ok', $errorLista !== '' ? $errorLista : 'No había nada que cambiar.'];
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
            foreach (array_merge($VIAJE, campos_viaje($salida['datos'], $VIAJE)) as [$clave, , $tipo]) {
                if (isset($_POST['v'][$clave])) {
                    $nuevo = trim((string) $_POST['v'][$clave]);
                    fijar($salida['datos'], $clave, $tipo === 'numero' ? (int) $nuevo : $nuevo);
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
    } elseif (($_POST['accion'] ?? '') === 'actualizar') {
        $aviso = actualizar_panel();
    } elseif (($_POST['accion'] ?? '') === 'secciones') {
        $aviso = guardar_secciones();
    } elseif (($_POST['accion'] ?? '') === 'preguntas') {
        $aviso = guardar_preguntas();
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
function huecos_fotos(array $viajes = [], array $etapas = [], array $home = []): array
{
    // Cuántas etapas y cuántos días hay ahora mismo: si Dani añade o quita en
    // las listas, aquí aparecen o desaparecen sus fotos.
    $nEtapas = $home ? count($home['ruta']['imagenes'] ?? []) : 5;
    $nDias = $home ? count($home['moto']['dias'] ?? []) : 6;
    $lista = [
        ['Portada', 'Foto de fondo en ordenador', 'home', 'hero.poster'],
        ['Portada', 'Foto de fondo en móvil', 'home', 'hero.posterMovil'],
        ['¿Y si esta vez Tailandia fuera diferente?', 'Foto grande a todo lo ancho', 'home', 'manifiesto.imagen'],
        ['Sabai sabai', 'Foto principal', 'home', 'sabai.imagen'],
        ['Sabai sabai', 'Foto pequeña superpuesta', 'home', 'sabai.imagen2'],
    ];
    for ($i = 0; $i < $nEtapas; $i++) {
        $lista[] = ['El viaje', 'Etapa ' . ($i + 1) . (isset($etapas[$i]) && $etapas[$i] !== '' ? ' · ' . $etapas[$i] : ''), 'home', 'ruta.imagenes.' . $i];
    }
    $lista[] = ['El viaje', 'Portada del vídeo', 'home', 'video.poster'];
    for ($i = 0; $i < $nDias; $i++) {
        $lista[] = ['La ruta en moto', 'Día ' . ($i + 1), 'home', 'moto.dias.' . $i . '.imagen'];
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
    // Una imagen con el tipo correcto pero rota tumbaría la publicación de la web,
    // así que se comprueba que de verdad se puede leer y tiene tamaño.
    $medidas = @getimagesize($_FILES['archivo']['tmp_name']);
    if (!$medidas || (int) $medidas[0] < 2 || (int) $medidas[1] < 2) {
        return ['', 'Esa imagen está dañada o es demasiado pequeña. Vuelve a exportarla y súbela otra vez.'];
    }
    return [$permitidos[$mime], ''];
}

/** Cambia la foto de un sitio de la web y/o su título y descripción. */
function guardar_hueco(): array
{
    $id = (string) ($_POST['hueco'] ?? '');
    $hogar = leer_json(HOME);
    $hueco = null;
    foreach (huecos_fotos(nombres_viajes(), [], $hogar['datos'] ?? []) as $h) {
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
if (!$sinConexion) {
    $CAMPOS = array_merge($CAMPOS, campos_auto($home['datos'], $sitio['datos'], $CAMPOS));
}
$preguntas = (!$sinConexion && $seccion === 'preguntas') ? leer_preguntas() : [];
$secciones = (!$sinConexion && $seccion === 'secciones') ? orden_secciones(leer_json(SECCIONES)) : [];
// Si el panel se ha quedado atrás respecto a GitHub, se avisa para poder ponerlo al día.
$panelViejo = (!$sinConexion && $seccion === 'secciones') ? array_keys(panel_pendiente()) : [];

require __DIR__ . '/vista.php';

/* ------------------------------------------------------------ pantalla */
function pintar_entrada(?array $aviso): void
{
    $titulo = 'Panel · Explora Siam';
    require __DIR__ . '/entrada.php';
}
