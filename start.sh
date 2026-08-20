#!/bin/sh
# Arranque del servidor PHP embebido.
# Railway inyecta $PORT en tiempo de ejecución; usamos 8080 como respaldo
# para ejecuciones locales. La expansión ocurre aquí, dentro del shell,
# para no depender de que Railway ejecute el comando de arranque con shell.
exec php -S "0.0.0.0:${PORT:-8080}" -t .
