# Installation

1. Sichere vorhandene Migrationen.
2. Kopiere die Dateien aus `laravel/database/migrations` in dein Laravel-Projekt.
3. Prüfe die `.env`-Verbindung zu PostgreSQL.
4. Führe aus:

```bash
php artisan optimize:clear
php artisan migrate:fresh
```

Für ein bestehendes Produktivsystem darf `migrate:fresh` nicht verwendet werden. Dieses Paket ist für das neue leere Projekt vorgesehen.

Danach prüfen:

```bash
php artisan db:show
php artisan migrate:status
```
