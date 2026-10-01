#!/bin/sh
set -e

# Start the Flask API internally on port 5000.
# PHP calls this through http://127.0.0.1:5000.
gunicorn --bind 127.0.0.1:5000 --workers 2 app:app &
FLASK_PID=$!

# Start the PHP website on Railway's public port.
php -S 0.0.0.0:${PORT} -t /app &
PHP_PID=$!

# If either server stops, stop the other one and exit.
trap 'kill $FLASK_PID $PHP_PID 2>/dev/null || true' INT TERM EXIT

wait -n $FLASK_PID $PHP_PID
STATUS=$?

kill $FLASK_PID $PHP_PID 2>/dev/null || true
exit $STATUS
