# Installation

## 1. Laravel installieren

Im Hauptordner:

```bash
rm -rf laravel
laravel new laravel
```

Danach Inhalte aus `laravel-template/` übernehmen:

```bash
cp -R laravel-template/. laravel/
cp laravel/.env.aktienki.example laravel/.env
cd laravel
php artisan key:generate
```

## 2. PostgreSQL konfigurieren

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=aktienki
DB_USERNAME=aktienki_app
DB_PASSWORD=DEIN_PASSWORT
```

Verbindung testen:

```bash
php artisan db:show
```

## 3. Python installieren

```bash
cd ../python-engine
python3.12 -m venv .venv
source .venv/bin/activate
pip install --upgrade pip
pip install -e '.[dev]'
cp .env.example .env
```

Für lokale Ausführung ohne Docker in `.env` ändern:

```dotenv
DATABASE_URL=postgresql+psycopg://aktienki_app:DEIN_PASSWORT@127.0.0.1:5432/aktienki
REDIS_URL=redis://127.0.0.1:6379/0
SHARED_STORAGE_PATH=../storage
MODEL_STORAGE_PATH=../storage/models
```

## 4. Dienste testen

Python-API:

```bash
uvicorn app.api.main:app --reload --port 8100
```

Python-Worker:

```bash
python -m app.workers.main
```
