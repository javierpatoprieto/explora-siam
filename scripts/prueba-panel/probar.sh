#!/usr/bin/env bash
# Recorre el panel como lo haría Dani: entrar, cambiar textos, añadir y quitar
# elementos, tocar preguntas, secciones y el viaje. Comprueba lo que queda escrito.
set -u

BASE="http://127.0.0.1:8111"
DIR="${1:-prueba}"
AQUI="$(cd "$(dirname "$0")" && pwd)"
CONTENIDO="$DIR/repo/content"
GALLETAS="$DIR/galletas.txt"
FALLOS=0
rm -f "$GALLETAS"

ok()    { echo "  OK    $1"; }
falla() { echo "  FALLO $1"; FALLOS=$((FALLOS + 1)); }
comprobar() { if [ "$2" = "$3" ]; then ok "$1"; else falla "$1 · esperaba «$3» y hay «$2»"; fi; }
contiene() { if grep -qF -- "$2" <<< "$3"; then ok "$1"; else falla "$1"; fi; }
no_contiene() { if grep -qF -- "$2" <<< "$3"; then falla "$1"; else ok "$1"; fi; }

pagina() { curl -s -b "$GALLETAS" -c "$GALLETAS" "$BASE/index.php$1"; }
enviar() { curl -s -b "$GALLETAS" -c "$GALLETAS" -X POST "$BASE/index.php$1" "${@:2}"; }
csrf()   { pagina "$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
dato()   { python3 "$AQUI/leer.py" "$1" "$2" 2>/dev/null; }
guardar() { local s="$1"; shift; enviar "?s=$s" -d "csrf=$(csrf "?s=$s")" -d "accion=$s" "$@" > /dev/null; }
sin_avisos() {
  if grep -qE "Warning:|Fatal error|Parse error|Notice:|Deprecated:" <<< "$2"; then
    falla "$1: PHP se queja"
    grep -oE "(Warning|Fatal error|Parse error|Notice|Deprecated):[^<]*" <<< "$2" | head -3
  else
    ok "$1: sin avisos de PHP"
  fi
}

echo "== Entrar =="
contiene "rechaza la contraseña mala" "Contraseña incorrecta" "$(enviar "" -d "clave=lo-que-sea")"
enviar "" -d "clave=prueba-local" > /dev/null
inicio=$(pagina "")
contiene "entra con la contraseña buena" "Textos de la web" "$inicio"
sin_avisos "inicio" "$inicio"

echo "== Cada pantalla abre =="
for s in textos fotos videos viaje preguntas secciones; do
  html=$(pagina "?s=$s")
  contiene "pantalla $s" "<h1" "$html"
  sin_avisos "pantalla $s" "$html"
done

echo "== Cambiar un texto =="
guardar textos -d "c[home|hero.titulo]=Titular de prueba"
comprobar "el titular se guarda" "$(dato "$CONTENIDO/home.json" hero.titulo)" "Titular de prueba"

echo "== Añadir un hueco y poder escribirlo (el fallo que vio Dani) =="
antes=$(dato "$CONTENIDO/home.json" incluye.incluido.cuantos)
guardar textos -d "lista_add=incluye.incluido"
comprobar "el hueco se añade" "$(dato "$CONTENIDO/home.json" incluye.incluido.cuantos)" "$((antes + 1))"
contiene "el hueco nuevo tiene campo donde escribir" "c[home|incluye.incluido.$antes]" "$(pagina "?s=textos")"
guardar textos -d "c[home|incluye.incluido.$antes]=Texto del hueco nuevo"
comprobar "se puede escribir en el hueco" "$(dato "$CONTENIDO/home.json" "incluye.incluido.$antes")" "Texto del hueco nuevo"

echo "== Mover y quitar de una lista =="
primera=$(dato "$CONTENIDO/home.json" cifras.0.etiqueta)
guardar textos -d "lista_baja=cifras" -d "lista_idx[cifras]=0"
comprobar "bajar mueve el elemento" "$(dato "$CONTENIDO/home.json" cifras.1.etiqueta)" "$primera"
guardar textos -d "lista_sube=cifras" -d "lista_idx[cifras]=1"
comprobar "subir lo devuelve a su sitio" "$(dato "$CONTENIDO/home.json" cifras.0.etiqueta)" "$primera"
n=$(dato "$CONTENIDO/home.json" incluye.incluido.cuantos)
guardar textos -d "lista_del=incluye.incluido" -d "lista_idx[incluye.incluido]=$((n - 1))"
comprobar "quitar borra el elegido" "$(dato "$CONTENIDO/home.json" incluye.incluido.cuantos)" "$((n - 1))"

echo "== Días de la ruta en moto (lista con foto) =="
dias=$(dato "$CONTENIDO/home.json" moto.dias.cuantos)
guardar textos -d "lista_add=moto.dias"
comprobar "se añade un día" "$(dato "$CONTENIDO/home.json" moto.dias.cuantos)" "$((dias + 1))"
foto=$(dato "$CONTENIDO/home.json" "moto.dias.$dias.imagen")
if [ -n "$foto" ]; then ok "el día nuevo hereda una foto"; else falla "el día nuevo se queda sin foto"; fi
contiene "el día nuevo se puede escribir" "c[home|moto.dias.$dias.titulo]" "$(pagina "?s=textos")"
guardar textos -d "lista_del=moto.dias" -d "lista_idx[moto.dias]=$dias"
comprobar "se quita el día" "$(dato "$CONTENIDO/home.json" moto.dias.cuantos)" "$dias"

echo "== Preguntas =="
PRIMERA=$(ls "$CONTENIDO/faqs" | head -1)
guardar preguntas -d "p[$PRIMERA][pregunta]=¿Pregunta cambiada?" -d "p[$PRIMERA][respuesta]=Respuesta cambiada." -d "p[$PRIMERA][orden]=3"
comprobar "cambia una pregunta" "$(dato "$CONTENIDO/faqs/$PRIMERA" pregunta)" "¿Pregunta cambiada?"
comprobar "cambia el orden" "$(dato "$CONTENIDO/faqs/$PRIMERA" orden)" "3"
comprobar "desmarcar «en portada» se guarda" "$(dato "$CONTENIDO/faqs/$PRIMERA" enHome)" "no"
guardar preguntas -d "nueva[pregunta]=¿Se puede pagar a plazos?" -d "nueva[respuesta]=Sí, hablamos y lo vemos." -d "nueva[enHome]=1"
if [ -f "$CONTENIDO/faqs/se-puede-pagar-a-plazos.json" ]; then ok "crea la pregunta nueva"; else falla "no crea la pregunta nueva"; fi
comprobar "la nueva sale en portada" "$(dato "$CONTENIDO/faqs/se-puede-pagar-a-plazos.json" enHome)" "si"
contiene "la nueva aparece en la lista" "¿Se puede pagar a plazos?" "$(pagina "?s=preguntas")"
guardar preguntas -d "borrar=se-puede-pagar-a-plazos.json"
if [ -f "$CONTENIDO/faqs/se-puede-pagar-a-plazos.json" ]; then falla "no borra la pregunta"; else ok "borra la pregunta"; fi

echo "== Secciones =="
guardar secciones -d "mostrar[manifiesto]=1"
comprobar "guarda las 12 secciones" "$(dato "$CONTENIDO/secciones.json" orden.cuantos)" "12"
comprobar "deja encendida la marcada" "$(dato "$CONTENIDO/secciones.json" orden.0.mostrar)" "si"
comprobar "apaga las no marcadas" "$(dato "$CONTENIDO/secciones.json" orden.2.mostrar)" "no"
guardar secciones -d "baja=manifiesto"
comprobar "bajar cambia el orden" "$(dato "$CONTENIDO/secciones.json" orden.1.id)" "manifiesto"
guardar secciones -d "sube=manifiesto"
comprobar "subir lo devuelve" "$(dato "$CONTENIDO/secciones.json" orden.0.id)" "manifiesto"

echo "== Viaje =="
VIAJE=$(ls "$CONTENIDO/salidas" | head -1)
enviar "?s=viaje" -d "csrf=$(csrf "?s=viaje")" -d "accion=viaje" -d "archivo=$VIAJE" \
  -d "v[duracion]=18 días" -d "v[plazas]=9" -d "v[itinerario.0.titulo]=Bangkok, 4 noches" -d "v[highlights.0]=750 km de curvas" > /dev/null
comprobar "cambia la duración" "$(dato "$CONTENIDO/salidas/$VIAJE" duracion)" "18 días"
comprobar "las plazas siguen siendo número" "$(dato "$CONTENIDO/salidas/$VIAJE" plazas)" "9"
comprobar "cambia una etapa del itinerario" "$(dato "$CONTENIDO/salidas/$VIAJE" itinerario.0.titulo)" "Bangkok, 4 noches"
comprobar "cambia un punto fuerte" "$(dato "$CONTENIDO/salidas/$VIAJE" highlights.0)" "750 km de curvas"

echo "== Textos de la página del viaje =="
guardar textos -d "c[sitio|paginaViaje.diaADia]=Etapa a etapa"
comprobar "cambia el titular del itinerario" "$(dato "$CONTENIDO/site.json" paginaViaje.diaADia)" "Etapa a etapa"
guardar textos -d "c[sitio|estilo.acento]=#b85c3c"
comprobar "cambia el color principal" "$(dato "$CONTENIDO/site.json" estilo.acento)" "#b85c3c"

echo "== Sesión y seguridad =="
suelto=$(curl -s -X POST "$BASE/index.php?s=textos" -d "accion=textos" -d "c[home|hero.titulo]=sin permiso")
contiene "sin sesión pide la contraseña" "clave" "$suelto"
malcsrf=$(enviar "?s=textos" -d "csrf=noesvalido" -d "accion=textos" -d "c[home|hero.titulo]=sin csrf")
contiene "rechaza el envío sin csrf válido" "sesión ha caducado" "$malcsrf"
comprobar "y no toca el contenido" "$(dato "$CONTENIDO/home.json" hero.titulo)" "Titular de prueba"
no_contiene "no enseña el token de GitHub" "token-de-prueba" "$(pagina "?s=textos")"
fuera=$(curl -s "$BASE/config.php")
no_contiene "config.php no se sirve tal cual" "PANEL_PASSWORD" "$fuera"

echo
echo "Guardados registrados (los últimos):"
tail -n 8 "$DIR/repo/.commits.log" 2>/dev/null || echo "  (ninguno)"
echo
if [ "$FALLOS" -eq 0 ]; then echo "RESULTADO: todo correcto"; else echo "RESULTADO: $FALLOS fallo(s)"; fi
exit "$FALLOS"
