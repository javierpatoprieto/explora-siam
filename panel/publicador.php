<?php
declare(strict_types=1);

/**
 * Publicación sin FTP.
 *
 * GitHub compila la web y la deja en la rama "publicado" con un manifiesto
 * (qué archivos hay y el hash de cada uno). Aquí, desde el propio hosting,
 * comparamos ese manifiesto con lo que ya está en public_html y nos
 * descargamos solo lo que ha cambiado.
 *
 * Ventaja sobre el FTP: no hay ninguna contraseña de hosting guardada en
 * ningún sitio. El hosting va a buscar la web, en vez de GitHub empujarla.
 */

const RAMA_PUBLICADA = 'publicado';
const MANIFIESTO = 'manifiesto.json';

/** Carpetas de public_html que la publicación no debe tocar jamás. */
const INTOCABLES = ['panel', 'video'];

/** Tope por tanda, para no agotar el tiempo de ejecución de PHP. */
const TANDA_ARCHIVOS = 12;
const TANDA_BYTES = 6291456; // 6 MB

function raiz_web(): string
{
    return dirname(__DIR__);
}

function registro_version(): string
{
    return __DIR__ . '/.publicado.json';
}

function version_local(): array
{
    $d = @json_decode((string) @file_get_contents(registro_version()), true);
    return is_array($d) ? $d : [];
}

function apuntar_version(string $commit, int $archivos): void
{
    @file_put_contents(registro_version(), json_encode([
        'commit' => $commit,
        'fecha' => date('c'),
        'archivos' => $archivos,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Descarga un archivo de la rama publicada.
 * Usa la API con el token (vale también si el repositorio pasa a privado) y,
 * si eso falla, prueba por la vía pública.
 */
function descargar_publicado(string $ruta): ?string
{
    $antiCache = '&_=' . time();
    $contenido = descargar(
        'https://api.github.com/repos/' . GITHUB_REPO . '/contents/' . rawurlencode_ruta($ruta) . '?ref=' . RAMA_PUBLICADA . $antiCache,
        [
            'Authorization: Bearer ' . GITHUB_TOKEN,
            'Accept: application/vnd.github.raw',
            'X-GitHub-Api-Version: 2022-11-28',
        ]
    );
    if ($contenido !== null) {
        return $contenido;
    }
    return descargar(
        'https://raw.githubusercontent.com/' . GITHUB_REPO . '/' . RAMA_PUBLICADA . '/' . rawurlencode_ruta($ruta) . '?_=' . time(),
        []
    );
}

function descargar(string $url, array $cabeceras): ?string
{
    $ch = curl_init($url);
    $cabeceras[] = 'User-Agent: panel-explora-siam';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $cabeceras,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $respuesta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($respuesta !== false && $codigo === 200) ? (string) $respuesta : null;
}

/** Lee el manifiesto de la rama publicada. */
function manifiesto_remoto(): ?array
{
    $texto = descargar_publicado(MANIFIESTO);
    if ($texto === null) {
        return null;
    }
    $d = json_decode($texto, true);
    return (is_array($d) && isset($d['archivos']) && is_array($d['archivos'])) ? $d : null;
}

/**
 * Convierte una ruta del manifiesto en una ruta absoluta del hosting,
 * o null si no es de fiar. Es la única puerta de escritura: todo pasa por aquí.
 */
function ruta_segura(string $relativa): ?string
{
    if ($relativa === '' || strlen($relativa) > 300) {
        return null;
    }
    if (strpos($relativa, "\0") !== false || strpos($relativa, '\\') !== false) {
        return null;
    }
    if ($relativa[0] === '/' || preg_match('#(^|/)\.\.?(/|$)#', $relativa)) {
        return null;
    }
    $primera = explode('/', $relativa)[0];
    if (in_array($primera, INTOCABLES, true)) {
        return null;
    }
    $destino = raiz_web() . '/' . $relativa;
    // El padre, ya resuelto, tiene que quedar dentro de public_html.
    $padre = dirname($destino);
    if (is_dir($padre)) {
        $real = realpath($padre);
        if ($real === false || strpos($real . '/', realpath(raiz_web()) . '/') !== 0) {
            return null;
        }
    }
    return $destino;
}

/**
 * Compara el manifiesto con lo que hay en el hosting.
 * Devuelve ['estado' => 'al-dia'|'pendiente'|'error', ...].
 */
function estado_publicacion(bool $forzar = false): array
{
    if (!function_exists('curl_init')) {
        return ['estado' => 'error', 'mensaje' => 'Este hosting no tiene cURL activado en PHP.'];
    }

    $manifiesto = manifiesto_remoto();
    if ($manifiesto === null) {
        return [
            'estado' => 'error',
            'mensaje' => 'Todavía no hay ninguna versión preparada. Si acabas de guardar, espera un par de minutos.',
        ];
    }

    $local = version_local();
    if (!$forzar && ($local['commit'] ?? '') === ($manifiesto['commit'] ?? '?') && ($local['commit'] ?? '') !== '') {
        return [
            'estado' => 'al-dia',
            'commit' => $manifiesto['commit'],
            'generado' => $manifiesto['generado'] ?? '',
            'pendientes' => [],
            'bytes' => 0,
            'total' => (int) ($manifiesto['total'] ?? 0),
        ];
    }

    $pendientes = [];
    $hashes = [];
    $bytes = 0;
    foreach ($manifiesto['archivos'] as $ruta => $info) {
        $destino = ruta_segura((string) $ruta);
        if ($destino === null) {
            continue;
        }
        if (is_file($destino) && hash_file('sha256', $destino) === ($info['sha256'] ?? '')) {
            continue;
        }
        $pendientes[] = (string) $ruta;
        $hashes[(string) $ruta] = (string) ($info['sha256'] ?? '');
        $bytes += (int) ($info['bytes'] ?? 0);
    }

    return [
        'estado' => $pendientes ? 'pendiente' : 'al-dia',
        'hashes' => $hashes,
        'commit' => $manifiesto['commit'] ?? '',
        'generado' => $manifiesto['generado'] ?? '',
        'pendientes' => $pendientes,
        'bytes' => $bytes,
        'total' => (int) ($manifiesto['total'] ?? count($manifiesto['archivos'])),
    ];
}

/**
 * Se descarga una tanda de archivos. Devuelve cuántos ha hecho y cuáles quedan,
 * para que la página vaya llamando hasta terminar.
 */
function publicar_tanda(array $pendientes, array $hashes = []): array
{
    $hechos = [];
    $fallos = [];
    $bytes = 0;

    foreach ($pendientes as $ruta) {
        if (count($hechos) >= TANDA_ARCHIVOS || $bytes >= TANDA_BYTES) {
            break;
        }
        $destino = ruta_segura((string) $ruta);
        if ($destino === null) {
            $fallos[] = ['ruta' => $ruta, 'motivo' => 'ruta no permitida'];
            continue;
        }
        $contenido = descargar_publicado((string) $ruta);
        if ($contenido === null) {
            $fallos[] = ['ruta' => $ruta, 'motivo' => 'no se ha podido descargar'];
            continue;
        }
        // GitHub sirve los archivos desde una caché que a veces va por detrás.
        // Si lo descargado no cuadra con el manifiesto, no lo escribimos: mejor
        // reintentar en un minuto que dejar la web descuadrada.
        $esperado = $hashes[$ruta] ?? '';
        if ($esperado !== '' && hash('sha256', $contenido) !== $esperado) {
            $fallos[] = ['ruta' => $ruta, 'motivo' => 'la copia descargada aún no está actualizada'];
            continue;
        }
        $padre = dirname($destino);
        if (!is_dir($padre) && !@mkdir($padre, 0755, true) && !is_dir($padre)) {
            $fallos[] = ['ruta' => $ruta, 'motivo' => 'no se ha podido crear la carpeta'];
            continue;
        }
        // Escritura atómica: primero a un temporal, luego se pone en su sitio.
        $temporal = $destino . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($temporal, $contenido) === false || !@rename($temporal, $destino)) {
            @unlink($temporal);
            $fallos[] = ['ruta' => $ruta, 'motivo' => 'no se ha podido escribir (¿permisos?)'];
            continue;
        }
        @chmod($destino, 0644);
        $hechos[] = $ruta;
        $bytes += strlen($contenido);
    }

    $quedan = array_values(array_diff($pendientes, $hechos, array_column($fallos, 'ruta')));
    return ['hechos' => $hechos, 'fallos' => $fallos, 'quedan' => $quedan, 'bytes' => $bytes];
}

/**
 * Borra los _astro/ viejos que ya no están en el manifiesto. Esos archivos
 * llevan un hash en el nombre, así que nadie los referencia ya. El resto de
 * public_html no se toca: si algo sobra, lo quita una persona a mano.
 */
function limpiar_sobrantes(array $manifiesto): int
{
    $carpeta = raiz_web() . '/_astro';
    if (!is_dir($carpeta)) {
        return 0;
    }
    $borrados = 0;
    foreach (scandir($carpeta) ?: [] as $nombre) {
        if ($nombre === '.' || $nombre === '..') {
            continue;
        }
        $relativa = '_astro/' . $nombre;
        if (isset($manifiesto['archivos'][$relativa])) {
            continue;
        }
        $ruta = $carpeta . '/' . $nombre;
        if (is_file($ruta) && @unlink($ruta)) {
            $borrados++;
        }
    }
    return $borrados;
}

function bytes_legibles(int $b): string
{
    if ($b >= 1048576) {
        return round($b / 1048576, 1) . ' MB';
    }
    if ($b >= 1024) {
        return round($b / 1024) . ' kB';
    }
    return $b . ' B';
}
