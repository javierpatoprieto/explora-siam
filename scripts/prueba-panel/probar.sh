#!/usr/bin/env bash
# Recorre el panel como lo haría Dani: entrar, cambiar textos, añadir y quitar
# elementos, tocar preguntas, secciones y el viaje. Comprueba lo que queda escrito.
set -uo pipefail

BASE="http://127.0.0.1:8111"
DIR="${1:-prueba}"
CONTENIDO="$DIR/repo/content"
GALLETAS="$DIR/galletas.txt"
FALLOS=0
rm -f "$GALLETAS"

ok()   { echo "  OK    $1"; }
falla() { echo "  FALLO $1"; FALLOS=$((FALLOS + 1)); }
comprobar() { if [ "$2" = "$3" ]; then ok "$1"; else falla "$1 (esperaba «$3», hay «$2»)"; fi; }

pagina() { curl -s -b "$GALLETAS" -c "$GALLETAS" "$BASE/index.php$1"; }
enviar() { curl -s -b "$GALLETAS" -c "$GALLETAS" -X POST "$BASE/index.php$1" "${@:2}"; }
csrf()   { pagina "$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
json()   { python3 -c "import json,sys;d=json.load(open('$1'));print(eval('d$2'))"; }
sin_avisos() {
  local donde="$1" html="$2"
  if echo "$html" | grep -qE "Warning:|Fatal error|Parse error|Notice:|Deprecated:"; then
    falla "$donde: PHP se queja"
    echo "$html" | grep -oE "(Warning|Fatal error|Parse error|Notice|Deprecated):[^<]*" | head -3
  else
    ok "$donde: sin avisos de PHP"
  fi
}

echo "== Entrar =="
mal=$(enviar "" -d "clave=lo-que-sea")
echo "$mal" | grep -q "Contraseña incorrecta" && ok "rechaza la contraseña mala" || falla "deja entrar con contraseña mala"
enviar "" -d "clave=prueba-local" -o /dev/null -w ""
inicio=$(pagina "")
echo "$inicio" | grep -q "Textos de la web" && ok "entra con la contraseña buena" || falla "no entra con la contraseña buena"
sin_avisos "inicio" "$inicio"

echo "== Cada pantalla abre =="
for s in textos fotos videos viaje preguntas secciones; do
  html=$(pagina "?s=$s")
  if echo "$html" | grep -qi "<h1"; then ok "pantalla $s"; else falla "pantalla $s no carga"; fi
  sin_avisos "pantalla $s" "$html"
done

echo "== Cambiar un texto =="
T=$(csrf "?s=textos")
enviar "?s=textos" -d "csrf=$T" -d "accion=textos" -d "c[home|hero.titulo]=Titular de prueba" -o /dev/null
comprobar "el titular se guarda" "$(json "$CONTENIDO/home.json" "['hero']['titulo']")" "Titular de prueba"

echo "== Añadir un hueco y poder escribirlo (el fallo que vio Dani) =="
antes=$(json "$CONTENIDO/home.json" "['incluye']['incluido'].__len__()")
T=$(csrf "?s=textos")
enviar "?s=textos" -d "csrf=$T" -d "accion=textos" -d "lista_add=incluye.incluido" -o /dev/null
despues=$(json "$CONTENIDO/home.json" "['incluye']['incluido'].__len__()")
comprobar "el hueco se añade" "$despues" "$((antes + 1))"
pagina "?s=textos" | grep -q "c\[home|incluye.incluido.$antes\]" && ok "el hueco nuevo tiene campo donde escribir" || falla "el hueco nuevo no tiene campo"
T=$(csrf "?s=textos")
enviar "?s=textos" -d "csrf=$T" -d "accion=textos" -d "c[home|incluye.incluido.$antes]=Texto del hueco nuevo" -o /dev/null
comprobar "se puede escribir en el hueco" "$(json "$CONTENIDO/home.json" "['incluye']['incluido'][$antes]")" "Texto del hueco nuevo"

echo "== Mover y quitar de una lista =="
primera=$(json "$CONTENIDO/home.json" "['cifras'][0]['etiqueta']")
T=$(csrf "?s=textos")
enviar "?s=textos" -d "csrf=$T" -d "accion=textos" -d "lista_baja=cifras" -d "lista_idx[cifras]=0" -o /dev/null
comprobar "bajar mueve el elemento" "$(json "$CONTENIDO/home.json" "['cifras'][1]['etiqueta']")" "$primera"
T=$(csrf "?s=textos")
enviar "?s=textos" -d "csrf=$T" -d "accion=textos" -d "lista_sube=cifras" -d "lista_idx[cifras]=1" -o /dev/null
comprobar "subir lo devuelve a su sitio" "$(json "$CONTENIDO/home.json" "['cifras'][0]['etiqueta']")" "$primera"
n=$(json "$CONTENIDO/home.json" "['incluye']['incluido'].__len__()")
T=$(csrf "?s=textos")
enviar "?s=textos" -d "csrf=$T" -d "accion=textos" -d "lista_del=incluye.incluido" -d "lista_idx[incluye.incluido]=$((n - 1))" -o /dev/null
comprobar "quitar borra el elegido" "$(json "$CONTENIDO/home.json" "['incluye']['incluido'].__len__()")" "$((n - 1))"

echo "== Días de la ruta en moto (lista con foto) =="
dias=$(json "$CONTENIDO/home.json" "['moto']['dias'].__len__()")
T=$(csrf "?s=textos")
enviar "?s=textos" -d "csrf=$T" -d "accion=textos" -d "lista_add=moto.dias" -o /dev/null
comprobar "se añade un día" "$(json "$CONTENIDO/home.json" "['moto']['dias'].__len__()")" "$((dias + 1))"
foto=$(json "$CONTENIDO/home.json" "['moto']['dias'][$dias]['imagen']")
[ -n "$foto" ] && ok "el día nuevo hereda una foto ($foto)" || falla "el día nuevo se queda sin foto"
T=$(csrf "?s=textos")
enviar "?s=textos" -d "csrf=$T" -d "accion=textos" -d "lista_del=moto.dias" -d "lista_idx[moto.dias]=$dias" -o /dev/null
comprobar "se quita el día" "$(json "$CONTENIDO/home.json" "['moto']['dias'].__len__()")" "$dias"

echo "== Preguntas =="
PRIMERA=$(ls "$CONTENIDO/faqs" | head -1)
T=$(csrf "?s=preguntas")
enviar "?s=preguntas" -d "csrf=$T" -d "accion=preguntas" -d "p[$PRIMERA][pregunta]=¿Pregunta cambiada?" -d "p[$PRIMERA][respuesta]=Respuesta cambiada." -d "p[$PRIMERA][orden]=3" -o /dev/null
comprobar "cambia una pregunta" "$(json "$CONTENIDO/faqs/$PRIMERA" "['pregunta']")" "¿Pregunta cambiada?"
T=$(csrf "?s=preguntas")
enviar "?s=preguntas" -d "csrf=$T" -d "accion=preguntas" -d "nueva[pregunta]=¿Se puede pagar a plazos?" -d "nueva[respuesta]=Sí, hablamos y lo vemos." -o /dev/null
[ -f "$CONTENIDO/faqs/se-puede-pagar-a-plazos.json" ] && ok "crea la pregunta nueva" || falla "no crea la pregunta nueva"
T=$(csrf "?s=preguntas")
enviar "?s=preguntas" -d "csrf=$T" -d "accion=preguntas" -d "borrar=se-puede-pagar-a-plazos.json" -o /dev/null
[ -f "$CONTENIDO/faqs/se-puede-pagar-a-plazos.json" ] && falla "no borra la pregunta" || ok "borra la pregunta"

echo "== Secciones =="
T=$(csrf "?s=secciones")
enviar "?s=secciones" -d "csrf=$T" -d "accion=secciones" -d "mostrar[manifiesto]=1" -o /dev/null
python3 - "$CONTENIDO/secciones.json" <<'PY'
import json, sys
d = json.load(open(sys.argv[1]))
ids = [s['id'] for s in d['orden']]
vis = {s['id']: s['mostrar'] for s in d['orden']}
print('  OK    guarda el orden (%d secciones)' % len(ids) if len(ids) >= 12 else '  FALLO orden corto')
print('  OK    apaga las no marcadas' if vis.get('cifras') is False and vis.get('manifiesto') is True else '  FALLO visibilidad: ' + str(vis))
PY
T=$(csrf "?s=secciones")
enviar "?s=secciones" -d "csrf=$T" -d "accion=secciones" -d "baja=manifiesto" -o /dev/null
comprobar "mover cambia el orden" "$(json "$CONTENIDO/secciones.json" "['orden'][1]['id']")" "manifiesto"

echo "== Viaje =="
VIAJE=$(ls "$CONTENIDO/salidas" | head -1)
T=$(csrf "?s=viaje")
enviar "?s=viaje" -d "csrf=$T" -d "accion=viaje" -d "archivo=$VIAJE" -d "v[duracion]=18 días" -d "v[itinerario.0.titulo]=Bangkok, 4 noches" -d "v[highlights.0]=750 km de curvas" -o /dev/null
comprobar "cambia la duración" "$(json "$CONTENIDO/salidas/$VIAJE" "['duracion']")" "18 días"
comprobar "cambia una etapa del itinerario" "$(json "$CONTENIDO/salidas/$VIAJE" "['itinerario'][0]['titulo']")" "Bangkok, 4 noches"
comprobar "cambia un punto fuerte" "$(json "$CONTENIDO/salidas/$VIAJE" "['highlights'][0]")" "750 km de curvas"

echo "== Textos de la página del viaje =="
T=$(csrf "?s=textos")
enviar "?s=textos" -d "csrf=$T" -d "accion=textos" -d "c[sitio|paginaViaje.diaADia]=Etapa a etapa" -o /dev/null
comprobar "cambia el titular del itinerario" "$(json "$CONTENIDO/site.json" "['paginaViaje']['diaADia']")" "Etapa a etapa"

echo "== Sesión y seguridad =="
sin=$(curl -s -X POST "$BASE/index.php?s=textos" -d "accion=textos" -d "c[home|hero.titulo]=sin permiso")
echo "$sin" | grep -q "Introduce la contraseña\|clave" && ok "sin sesión no deja guardar" || falla "deja guardar sin sesión"
T=$(csrf "?s=textos")
malcsrf=$(enviar "?s=textos" -d "csrf=noesvalido" -d "accion=textos" -d "c[home|hero.titulo]=sin csrf")
echo "$malcsrf" | grep -q "sesión ha caducado" && ok "rechaza el envío sin csrf válido" || falla "acepta un csrf inválido"
comprobar "el titular no se tocó" "$(json "$CONTENIDO/home.json" "['hero']['titulo']")" "Titular de prueba"

echo
echo "Guardados registrados:"
sed -n '1,12p' "$DIR/repo/.commits.log" 2>/dev/null || echo "  (ninguno)"
echo
if [ "$FALLOS" -eq 0 ]; then echo "RESULTADO: todo correcto"; else echo "RESULTADO: $FALLOS fallo(s)"; fi
exit "$FALLOS"
