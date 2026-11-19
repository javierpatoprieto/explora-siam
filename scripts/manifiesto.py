"""Escribe manifiesto.json con el hash de cada archivo de la web compilada.

Se ejecuta dentro de dist/ desde el flujo "Preparar la web para el hosting".
El hosting lee este archivo para saber qué tiene que descargarse.
"""

import datetime
import hashlib
import json
import os
import sys

SALIDA = 'manifiesto.json'


def main() -> int:
    commit = sys.argv[1] if len(sys.argv) > 1 else ''
    archivos = {}

    for raiz, _, nombres in os.walk('.'):
        for nombre in nombres:
            ruta = os.path.relpath(os.path.join(raiz, nombre), '.').replace(os.sep, '/')
            if ruta == SALIDA or ruta.startswith('.git/'):
                continue
            with open(ruta, 'rb') as f:
                sha = hashlib.sha256(f.read()).hexdigest()
            archivos[ruta] = {'sha256': sha, 'bytes': os.path.getsize(ruta)}

    if not archivos:
        print('No hay archivos que publicar: algo ha fallado en la compilación.', file=sys.stderr)
        return 1

    datos = {
        'commit': commit,
        'generado': datetime.datetime.now(datetime.timezone.utc).isoformat(timespec='seconds'),
        'total': len(archivos),
        'archivos': dict(sorted(archivos.items())),
    }
    with open(SALIDA, 'w', encoding='utf-8') as f:
        json.dump(datos, f, ensure_ascii=False, indent=1)

    print(f'{len(archivos)} archivos en el manifiesto')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
