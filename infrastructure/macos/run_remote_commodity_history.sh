#!/bin/zsh

set -eu

readonly REMOTE_HOST="root@217.154.240.14"
readonly IDENTITY_FILE="/Users/aktienki/.ssh/aktienki_server"

exec /usr/bin/ssh \
    -i "${IDENTITY_FILE}" \
    -o BatchMode=yes \
    -o ConnectTimeout=20 \
    -o ServerAliveInterval=30 \
    -o ServerAliveCountMax=2 \
    "${REMOTE_HOST}" \
    "cd /home/aktienki/AktienKI/laravel && /usr/bin/php artisan commodities:refresh-history --days=365 --no-interaction"
