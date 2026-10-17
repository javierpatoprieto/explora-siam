"""Lee un dato de un JSON: leer.py archivo clave.con.puntos (los números son posiciones)."""
import json, sys

d = json.load(open(sys.argv[1], encoding='utf-8'))
for parte in sys.argv[2].split('.'):
    if parte == '':
        continue
    if parte == 'cuantos':
        d = len(d)
        break
    d = d[int(parte)] if parte.isdigit() else d[parte]
print(d if not isinstance(d, bool) else ('si' if d else 'no'))
