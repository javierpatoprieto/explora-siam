<?php
declare(strict_types=1);

/** Utilidades del panel: sesión, seguridad y acceso a GitHub. */

function panel_arrancar(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/panel/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    // Caduca a las dos horas de inactividad.
    if (isset($_SESSION['visto']) && time() - $_SESSION['visto'] > 7200) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['visto'] = time();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
}

function dentro(): bool
{
    return !empty($_SESSION['ok']);
}

function csrf(): string
{
    return $_SESSION['csrf'] ?? '';
}

function csrf_valido(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

/** Frena la fuerza bruta: cinco intentos fallidos bloquean quince minutos. */
function intentos_ruta(): string
{
    return __DIR__ . '/.intentos';
}

function bloqueado(): int
{
    $d = @json_decode((string) @file_get_contents(intentos_ruta()), true);
    if (!is_array($d) || ($d['n'] ?? 0) < 5) {
        return 0;
    }
    $quedan = 900 - (time() - (int) ($d['t'] ?? 0));
    return $quedan > 0 ? $quedan : 0;
}

function apuntar_fallo(): void
{
    $d = @json_decode((string) @file_get_contents(intentos_ruta()), true);
    $n = (is_array($d) && time() - (int) ($d['t'] ?? 0) < 900) ? (int) ($d['n'] ?? 0) : 0;
    @file_put_contents(intentos_ruta(), json_encode(['n' => $n + 1, 't' => time()]));
}

function limpiar_fallos(): void
{
    @unlink(intentos_ruta());
}

/** Llamada a la API de GitHub. Devuelve [código, datos]. */
function gh(string $metodo, string $ruta, ?array $cuerpo = null): array
{
    $ch = curl_init('https://api.github.com' . $ruta);
    $cabeceras = [
        'Authorization: Bearer ' . GITHUB_TOKEN,
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: panel-explora-siam',
    ];
    if ($cuerpo !== null) {
        $cabeceras[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $cabeceras,
        CURLOPT_TIMEOUT => 45,
    ]);
    $respuesta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($respuesta === false) {
        return [0, ['message' => $error ?: 'Sin conexión con GitHub']];
    }
    return [$codigo, json_decode((string) $respuesta, true) ?? []];
}

function repo(): string
{
    return '/repos/' . GITHUB_REPO;
}

/** Lee un archivo del repositorio. Devuelve ['contenido' => string, 'sha' => string] o null. */
function leer_archivo(string $ruta): ?array
{
    [$codigo, $datos] = gh('GET', repo() . '/contents/' . rawurlencode_ruta($ruta) . '?ref=' . GITHUB_BRANCH);
    if ($codigo !== 200 || !isset($datos['content'])) {
        return null;
    }
    return ['contenido' => base64_decode(str_replace("\n", '', $datos['content'])), 'sha' => $datos['sha']];
}

function leer_json(string $ruta): ?array
{
    $a = leer_archivo($ruta);
    if (!$a) {
        return null;
    }
    $j = json_decode($a['contenido'], true);
    return is_array($j) ? ['datos' => $j, 'sha' => $a['sha']] : null;
}

/** Escribe (o crea) un archivo y devuelve [ok, mensaje]. */
function guardar_archivo(string $ruta, string $contenido, ?string $sha, string $mensaje): array
{
    $cuerpo = [
        'message' => $mensaje,
        'content' => base64_encode($contenido),
        'branch' => GITHUB_BRANCH,
    ];
    if ($sha) {
        $cuerpo['sha'] = $sha;
    }
    [$codigo, $datos] = gh('PUT', repo() . '/contents/' . rawurlencode_ruta($ruta), $cuerpo);
    if ($codigo === 200 || $codigo === 201) {
        return [true, ''];
    }
    return [false, $datos['message'] ?? ('Error ' . $codigo)];
}

function guardar_json(string $ruta, array $datos, ?string $sha, string $mensaje): array
{
    $texto = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    return guardar_archivo($ruta, $texto, $sha, $mensaje);
}

function rawurlencode_ruta(string $ruta): string
{
    return implode('/', array_map('rawurlencode', explode('/', $ruta)));
}

/** Lista los archivos de una carpeta del repositorio. */
function listar(string $carpeta): array
{
    [$codigo, $datos] = gh('GET', repo() . '/contents/' . rawurlencode_ruta($carpeta) . '?ref=' . GITHUB_BRANCH);
    return $codigo === 200 && is_array($datos) ? $datos : [];
}

/** Devuelve el valor de una ruta con puntos: 'hero.titulo'. */
function valor(array $datos, string $camino, string $porDefecto = ''): string
{
    foreach (explode('.', $camino) as $parte) {
        if (!is_array($datos) || !array_key_exists($parte, $datos)) {
            return $porDefecto;
        }
        $datos = $datos[$parte];
    }
    return is_scalar($datos) ? (string) $datos : $porDefecto;
}

/** Fija el valor de una ruta con puntos. */
function fijar(array &$datos, string $camino, $valor): void
{
    $partes = explode('.', $camino);
    $ref = &$datos;
    foreach ($partes as $i => $parte) {
        if ($i === count($partes) - 1) {
            $ref[$parte] = $valor;
            return;
        }
        if (!isset($ref[$parte]) || !is_array($ref[$parte])) {
            $ref[$parte] = [];
        }
        $ref = &$ref[$parte];
    }
}

/** Carpeta de vídeos del hosting: public_html/video */
function carpeta_videos(): string
{
    $ruta = dirname(__DIR__) . '/video';
    if (!is_dir($ruta)) {
        @mkdir($ruta, 0755, true);
    }
    return $ruta;
}

/** Vídeos que ya están subidos al hosting. */
function videos_subidos(): array
{
    $ruta = carpeta_videos();
    $lista = [];
    foreach (@scandir($ruta) ?: [] as $f) {
        if (str_ends_with(strtolower($f), '.mp4')) {
            $lista[] = ['nombre' => $f, 'peso' => (int) @filesize($ruta . '/' . $f)];
        }
    }
    return $lista;
}

function e(?string $t): string
{
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}
