# Theme Switcher Fix

Ersetze vollständig:

`resources/views/components/theme-switcher.blade.php`

Ergänze den Inhalt aus:

`routes/theme-routes.php`

in deine bestehende:

`routes/web.php`

Danach:

```bash
php artisan optimize:clear
php artisan route:list | grep settings.theme
```
