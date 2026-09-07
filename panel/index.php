<?php
declare(strict_types=1);

/**
 * Panel de Explora Siam.
 * Edita los textos, las fotos y los vídeos de la web. Cada cambio se guarda en el
 * repositorio y la web se vuelve a publicar sola en un par de minutos.
 */

if (!file_exists(__DIR__ . '/config.php')) {
    exit('Falta config.php. Copia config.example.php como config.php y rellena los datos.');
}
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/publicador.php';

// Evita dejar el panel abierto con los valores de ejemplo.
if (PANEL_PASSWORD === 'cambia-esta-contrasena' || str_starts_with(GITHUB_TOKEN, 'github_pat_...')) {
    exit('Panel sin configurar: edita panel/config.php y pon la contraseña y el token de GitHub.');
}

panel_arrancar();

const HOME = 'content/home.json';
const SITIO = 'content/site.json';
const IMGS = 'src/assets/img';
const VIDEOS = 'public/video';

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
if (isset($_GET['foto'])) {
    $ruta = IMGS . '/' . basename((string) $_GET['foto']);
    $archivo = leer_archivo($ruta);
    if (!$archivo) {
        http_response_code(404);
        exit;
    }
    $tipo = str_ends_with($ruta, '.png') ? 'image/png' : 'image/jpeg';
    header('Content-Type: ' . $tipo);
    header('Cache-Control: private, max-age=300');
    echo $archivo['contenido'];
    exit;
}

/* ------------------------------------------------ publicación en el hosting */
// La página llama aquí por tandas hasta que la web queda al día.
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $accion = (string) $_GET['api'];

    if ($accion === 'estado') {
        echo json_encode(estado_resumido(estado_publicacion()));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido()) {
        http_response_code(403);
        echo json_encode(['estado' => 'error', 'mensaje' => 'Vuelve a cargar la página.']);
        exit;
    }

    if ($accion === 'empezar') {
        $estado = estado_publicacion(true);
        if ($estado['estado'] === 'error') {
            echo json_encode($estado);
            exit;
        }
        $_SESSION['pub'] = [
            'pendientes' => $estado['pendientes'],
            'hashes' => $estado['hashes'] ?? [],
            'commit' => $estado['commit'],
            'total' => count($estado['pendientes']),
            'hechos' => 0,
            'fallos' => [],
        ];
        echo json_encode(estado_resumido($estado));
        exit;
    }

    if ($accion === 'tanda') {
        $pub = $_SESSION['pub'] ?? null;
        if (!is_array($pub)) {
            echo json_encode(['estado' => 'error', 'mensaje' => 'Empieza la publicación otra vez.']);
            exit;
        }
        if ($pub['pendientes']) {
            $r = publicar_tanda($pub['pendientes'], $pub['hashes'] ?? []);
            $pub['pendientes'] = $r['quedan'];
            $pub['hechos'] += count($r['hechos']);
            $pub['fallos'] = array_merge($pub['fallos'], $r['fallos']);
            $_SESSION['pub'] = $pub;
        }
        if ($pub['pendientes']) {
            echo json_encode([
                'estado' => 'en-marcha',
                'hechos' => $pub['hechos'],
                'total' => $pub['total'],
                'quedan' => count($pub['pendientes']),
            ]);
            exit;
        }
        // Terminado: quitamos los _astro viejos y apuntamos la versión.
        $manifiesto = manifiesto_remoto();
        $limpiados = $manifiesto ? limpiar_sobrantes($manifiesto) : 0;
        if (!$pub['fallos']) {
            apuntar_version((string) $pub['commit'], (int) $pub['total']);
        }
        unset($_SESSION['pub']);
        echo json_encode([
            'estado' => $pub['fallos'] ? 'con-fallos' : 'listo',
            'hechos' => $pub['hechos'],
            'total' => $pub['total'],
            'limpiados' => $limpiados,
            'fallos' => array_slice($pub['fallos'], 0, 5),
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['estado' => 'error', 'mensaje' => 'Acción desconocida.']);
    exit;
}

function estado_resumido(array $e): array
{
    // Ojo: los hashes se quedan en el servidor, no hacen falta en el navegador.
    return [
        'estado' => $e['estado'],
        'mensaje' => $e['mensaje'] ?? '',
        'pendientes' => count($e['pendientes'] ?? []),
        'bytes' => $e['bytes'] ?? 0,
        'peso' => bytes_legibles((int) ($e['bytes'] ?? 0)),
        'commit' => substr((string) ($e['commit'] ?? ''), 0, 7),
        'generado' => $e['generado'] ?? '',
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
    'Frase destacada' => [
        ['home', 'statement.antes', 'Frase, primera parte', 'linea'],
        ['home', 'statement.medio', 'Frase, parte central', 'linea'],
        ['home', 'statement.despues', 'Frase, parte final', 'linea'],
        ['home', 'statement.texto', 'Texto pequeño debajo', 'parrafo'],
    ],
    'La ruta' => [
        ['home', 'ruta.etiqueta', 'Etiqueta', 'linea'],
        ['home', 'ruta.titulo', 'Titular', 'linea'],
        ['home', 'video.titulo', 'Titular del vídeo', 'linea'],
        ['home', 'video.etiqueta', 'Etiqueta del vídeo', 'linea'],
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
    'Precio' => [
        ['home', 'precio.titulo', 'Precio grande', 'linea'],
        ['home', 'precio.nota', 'Nota bajo el precio', 'parrafo'],
        ['home', 'incluye.enlace', 'Texto del enlace a WhatsApp', 'linea'],
    ],
    'Cierre' => [
        ['home', 'cta.titulo', 'Titular', 'linea'],
        ['home', 'cta.subtitulo', 'Texto', 'parrafo'],
        ['home', 'cta.boton', 'Botón', 'linea'],
        ['home', 'cta.microcopy', 'Frase bajo el botón', 'linea'],
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

$VIAJE = [
    ['fechas', 'Fechas', 'linea'],
    ['duracion', 'Duración', 'linea'],
    ['plazas', 'Plazas totales', 'numero'],
    ['disponibles', 'Plazas libres', 'numero'],
    ['precio', 'Precio en euros', 'numero'],
    ['notaPrecio', 'Nota del precio', 'linea'],
    ['descripcion', 'Descripción', 'parrafo'],
];

$seccion = $_GET['s'] ?? 'textos';

/* ------------------------------------------------------------- guardar */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && dentro()) {
    if (!csrf_valido()) {
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
    } elseif (($_POST['accion'] ?? '') === 'foto') {
        $aviso = subir_medio('foto');
    } elseif (($_POST['accion'] ?? '') === 'video') {
        $aviso = subir_medio('video');
    }
}

/** Sube una foto (sustituyendo otra) o un vídeo. */
function subir_medio(string $tipo): array
{
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        return ['mal', 'No ha llegado ningún archivo. ¿Pesa demasiado?'];
    }
    $tmp = $_FILES['archivo']['tmp_name'];
    $peso = (int) $_FILES['archivo']['size'];
    $limite = $tipo === 'foto' ? 8 * 1024 * 1024 : 300 * 1024 * 1024;
    if ($peso > $limite) {
        return ['mal', 'El archivo pesa ' . round($peso / 1048576, 1) . ' MB y el máximo son ' . round($limite / 1048576) . ' MB.'];
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if ($tipo === 'foto') {
        $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($permitidos[$mime])) {
            return ['mal', 'Solo se admiten imágenes JPG, PNG o WEBP.'];
        }
        $destino = IMGS . '/' . basename((string) ($_POST['sustituye'] ?? ''));
        if ($destino === IMGS . '/') {
            return ['mal', 'No se ha indicado qué foto sustituir.'];
        }
        $actual = leer_archivo($destino);
        [$ok, $err] = guardar_archivo($destino, (string) file_get_contents($tmp), $actual['sha'] ?? null, 'Panel: nueva foto ' . basename($destino));
        return $ok
            ? ['ok', 'Foto sustituida. La web se actualiza en unos ' . MINUTOS_PUBLICACION . ' minutos.']
            : ['mal', 'No se pudo subir: ' . $err];
    }
    if ($mime !== 'video/mp4') {
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
    $donde = (string) ($_POST['donde'] ?? 'hero');
    $home = leer_json(HOME);
    if ($home) {
        fijar($home['datos'], $donde === 'hero' ? 'hero.videoMp4' : 'video.mp4', '/video/' . $nombre);
        guardar_json(HOME, $home['datos'], $home['sha'], 'Panel: vídeo en ' . $donde);
    }
    return ['ok', 'Vídeo subido (' . round($peso / 1048576, 1) . ' MB) y colocado. La web se actualiza en unos ' . MINUTOS_PUBLICACION . ' minutos.'];
}

/* --------------------------------------------------------------- datos */
$home = leer_json(HOME);
$sitio = leer_json(SITIO);
$sinConexion = !$home || !$sitio;
$fotos = $sinConexion ? [] : array_values(array_filter(listar(IMGS), fn ($f) => ($f['type'] ?? '') === 'file'));
$salidas = $sinConexion ? [] : array_values(array_filter(listar('content/salidas'), fn ($f) => str_ends_with($f['name'] ?? '', '.json')));

require __DIR__ . '/vista.php';

/* ------------------------------------------------------------ pantalla */
function pintar_entrada(?array $aviso): void
{
    $titulo = 'Panel · Explora Siam';
    require __DIR__ . '/entrada.php';
}
