#!/usr/bin/env bash
# Decide si Vercel tiene que publicar este cambio.
#   salir con 0        → no publicar
#   salir distinto de 0 → publicar
#
# Publicar cuesta: el plan tiene un tope diario. Así que solo se publica lo que
# toca la web, y solo desde main (la rama de informes de pruebas no es la web).
set -u

rama="${VERCEL_GIT_COMMIT_REF:-main}"
if [ "$rama" != "main" ]; then
  echo "Rama $rama: no es la web, no se publica."
  exit 0
fi

# Sin cambio anterior con el que comparar, se publica por si acaso.
if ! git rev-parse HEAD^ > /dev/null 2>&1; then
  echo "Sin historial con el que comparar: se publica."
  exit 1
fi

if git diff --quiet HEAD^ HEAD -- . ':(exclude)panel' ':(exclude)scripts' ':(exclude).github' ':(exclude)docs' ':(exclude)design' ':(exclude)dossier'; then
  echo "El cambio no toca la web (panel, pruebas o flujos): no se publica."
  exit 0
fi

echo "El cambio toca la web: se publica."
exit 1
