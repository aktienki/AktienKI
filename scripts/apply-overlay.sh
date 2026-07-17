#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-$(pwd)}"

if [[ ! -d "$ROOT/laravel" || ! -d "$ROOT/python-engine" ]]; then
  echo "Fehler: Bitte das Skript im AktienKI-Hauptordner ausführen oder den Projektpfad übergeben."
  exit 1
fi

find "$ROOT" -name '.DS_Store' -type f -delete

echo "macOS-Metadateien entfernt."
cd "$ROOT/laravel"
php artisan optimize:clear
php artisan migrate --force
php artisan aktienki:version

echo "Overlay 001 wurde erfolgreich angewendet."
