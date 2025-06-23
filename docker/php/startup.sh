#!/bin/sh

# Wait a moment for the container to be fully ready
sleep 2

# Clear and warm up cache
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod

# Set proper permissions
chown -R symfony:symfony var/

# Start FrankenPHP
exec frankenphp run --config /etc/caddy/Caddyfile /app/public