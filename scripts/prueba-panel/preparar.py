"""Monta una copia del panel que trabaja contra una carpeta local en vez de GitHub.

Sirve para probar el panel entero (entrar, escribir, añadir, borrar) sin tocar
la web real ni necesitar el token: el "repositorio" es una copia del contenido.
"""
import io, os, shutil, sys

RAIZ = os.path.abspath(sys.argv[1] if len(sys.argv) > 1 else '.')
PRUEBA = os.path.abspath(sys.argv[2] if len(sys.argv) > 2 else 'prueba')

shutil.rmtree(PRUEBA, ignore_errors=True)
os.makedirs(PRUEBA)
shutil.copytree(RAIZ + '/panel', PRUEBA + '/panel')
shutil.copytree(RAIZ + '/content', PRUEBA + '/repo/content')
# las fotos van de verdad: así se puede compilar la web con lo que escriba el panel
shutil.copytree(RAIZ + '/src/assets/img', PRUEBA + '/repo/src/assets/img')
os.makedirs(PRUEBA + '/video', exist_ok=True)

io.open(PRUEBA + '/panel/config.php', 'w', encoding='utf-8', newline='\n').write("""<?php
define('PANEL_PASSWORD', 'prueba-local');
define('GITHUB_TOKEN', 'token-de-prueba');
define('GITHUB_REPO', 'javierpatoprieto/explora-siam');
define('GITHUB_BRANCH', 'main');
define('MINUTOS_PUBLICACION', 2);
""")

p = PRUEBA + '/panel/lib.php'
s = io.open(p, encoding='utf-8', newline='').read().replace('\r\n', '\n')
inicio = s.index('function gh(string $metodo, string $ruta, ?array $cuerpo = null): array')
fin = s.index('\nfunction repo(): string')
falso = r'''function gh(string $metodo, string $ruta, ?array $cuerpo = null): array
{
    // PRUEBA LOCAL: en vez de GitHub, lee y escribe en ../repo
    $raiz = dirname(__DIR__) . '/repo';
    $ruta = urldecode($ruta);
    if (preg_match('#/commits/[^/]+/statuses#', $ruta)) {
        return [200, [['context' => 'Vercel', 'state' => 'success']]];
    }
    if (preg_match('#/commits/#', $ruta)) {
        return [200, ['sha' => 'abc1234', 'commit' => ['committer' => ['date' => gmdate('c')]]]];
    }
    if (!preg_match('#/contents/([^?]+)#', $ruta, $m)) {
        return [200, []];
    }
    $archivo = $raiz . '/' . rtrim($m[1], '/');
    if ($metodo === 'GET') {
        if (is_dir($archivo)) {
            $lista = [];
            foreach (scandir($archivo) as $f) {
                if ($f[0] !== '.') {
                    $lista[] = ['name' => $f, 'type' => 'file'];
                }
            }
            return [200, $lista];
        }
        if (!is_file($archivo)) {
            return [404, ['message' => 'Not Found']];
        }
        $contenido = (string) file_get_contents($archivo);
        return [200, ['content' => base64_encode($contenido), 'sha' => substr(sha1($contenido), 0, 40), 'encoding' => 'base64']];
    }
    if ($metodo === 'PUT') {
        @mkdir(dirname($archivo), 0777, true);
        file_put_contents($archivo, base64_decode((string) ($cuerpo['content'] ?? '')));
        file_put_contents($raiz . '/.commits.log', 'PUT ' . $m[1] . ' :: ' . ($cuerpo['message'] ?? '') . "\n", FILE_APPEND);
        return [200, ['content' => ['sha' => 'nuevo']]];
    }
    if ($metodo === 'DELETE') {
        @unlink($archivo);
        file_put_contents($raiz . '/.commits.log', 'DELETE ' . $m[1] . ' :: ' . ($cuerpo['message'] ?? '') . "\n", FILE_APPEND);
        return [200, []];
    }
    return [200, []];
}
'''
s = s[:inicio] + falso + s[fin:]
io.open(p, 'w', encoding='utf-8', newline='\n').write(s)

# El "repositorio" guarda el panel ya preparado, para poder probar que el panel
# se actualiza solo. La copia instalada se deja desfasada a propósito.
shutil.copytree(PRUEBA + '/panel', PRUEBA + '/repo/panel')
estilo = PRUEBA + '/panel/estilo.php'
io.open(estilo, 'a', encoding='utf-8', newline='\n').write('\n<!-- version vieja -->\n')

print('panel de prueba listo en', PRUEBA)
