<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Kostenloser Einstieg',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'max_watchlist_items' => 10,
                'max_predictions_per_day' => 20,
                'max_portfolio_positions' => 10,
                'has_premium_indices' => false,
                'has_advanced_signals' => false,
                'has_exports' => false,
                'has_email_alerts' => false,
                'features' => ['Basis-Signale', 'Begrenzte Watchlist'],
            ],
            [
                'slug' => 'basic',
                'name' => 'Basic',
                'description' => 'Mehr Watchlist und Signale',
                'price_monthly' => 9.90,
                'price_yearly' => 99.00,
                'max_watchlist_items' => 50,
                'max_predictions_per_day' => 250,
                'max_portfolio_positions' => 50,
                'has_premium_indices' => false,
                'has_advanced_signals' => true,
                'has_exports' => false,
                'has_email_alerts' => true,
                'features' => ['Mehr Signale', 'E-Mail-Alarme'],
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'Voller Zugriff für aktive Nutzer',
                'price_monthly' => 19.90,
                'price_yearly' => 199.00,
                'max_watchlist_items' => 250,
                'max_predictions_per_day' => 1000,
                'max_portfolio_positions' => 250,
                'has_premium_indices' => true,
                'has_advanced_signals' => true,
                'has_exports' => true,
                'has_email_alerts' => true,
                'features' => ['Premium-Indizes', 'Erweiterte Signale', 'Exports'],
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Individuelle Lösung',
                'price_monthly' => 99.00,
                'price_yearly' => 999.00,
                'max_watchlist_items' => null,
                'max_predictions_per_day' => null,
                'max_portfolio_positions' => null,
                'has_premium_indices' => true,
                'has_advanced_signals' => true,
                'has_exports' => true,
                'has_email_alerts' => true,
                'features' => ['Alles inklusive', 'Individuelle Limits'],
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'price_monthly' => $plan['price_monthly'],
                    'price_yearly' => $plan['price_yearly'],
                    'currency' => 'EUR',
                    'max_watchlist_items' => $plan['max_watchlist_items'],
                    'max_predictions_per_day' => $plan['max_predictions_per_day'],
                    'max_portfolio_positions' => $plan['max_portfolio_positions'],
                    'has_premium_indices' => $plan['has_premium_indices'],
                    'has_advanced_signals' => $plan['has_advanced_signals'],
                    'has_exports' => $plan['has_exports'],
                    'has_email_alerts' => $plan['has_email_alerts'],
                    'features' => json_encode($plan['features']),
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $legalDocuments = [
            ['type' => 'terms', 'slug' => 'agb', 'title' => 'Allgemeine Geschäftsbedingungen', 'version' => '1.0', 'content' => 'Platzhalter für AGB. Bitte rechtlich prüfen lassen.'],
            ['type' => 'privacy', 'slug' => 'datenschutz', 'title' => 'Datenschutzerklärung', 'version' => '1.0', 'content' => 'Platzhalter für Datenschutzerklärung. Bitte rechtlich prüfen lassen.'],
            ['type' => 'risk_notice', 'slug' => 'risikohinweis', 'title' => 'Risikohinweis', 'version' => '1.0', 'content' => 'Keine Anlageberatung. Aktienkurse können schwanken. Bitte rechtlich prüfen lassen.'],
            ['type' => 'imprint', 'slug' => 'impressum', 'title' => 'Impressum', 'version' => '1.0', 'content' => 'Platzhalter für Impressum. Bitte rechtlich prüfen lassen.'],
            ['type' => 'cookie_notice', 'slug' => 'cookie-hinweis', 'title' => 'Cookie-Hinweis', 'version' => '1.0', 'content' => 'Platzhalter für Cookie-Hinweis. Bitte rechtlich prüfen lassen.'],
            ['type' => 'disclaimer', 'slug' => 'disclaimer', 'title' => 'Disclaimer', 'version' => '1.0', 'content' => 'Platzhalter für Haftungsausschluss. Bitte rechtlich prüfen lassen.'],
        ];

        foreach ($legalDocuments as $document) {
            DB::table('legal_documents')->updateOrInsert(
                ['type' => $document['type'], 'version' => $document['version'], 'language' => 'de'],
                [
                    'slug' => $document['slug'],
                    'title' => $document['title'],
                    'content' => $document['content'],
                    'active' => true,
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $settings = [
            ['key' => 'app.name', 'value' => 'AktienKI', 'type' => 'string', 'description' => 'Name der Anwendung'],
            ['key' => 'legal.current_terms_version', 'value' => '1.0', 'type' => 'string', 'description' => 'Aktuelle AGB-Version'],
            ['key' => 'legal.current_privacy_version', 'value' => '1.0', 'type' => 'string', 'description' => 'Aktuelle Datenschutz-Version'],
            ['key' => 'legal.current_risk_notice_version', 'value' => '1.0', 'type' => 'string', 'description' => 'Aktuelle Risikohinweis-Version'],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'description' => $setting['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@aktienki.local'],
            [
                'name' => 'AktienKI Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'subscription_level' => 'enterprise',
                'active' => true,
                'accepted_terms' => true,
                'accepted_terms_at' => $now,
                'accepted_privacy' => true,
                'accepted_privacy_at' => $now,
                'accepted_risk_notice' => true,
                'accepted_risk_notice_at' => $now,
                'accepted_cookie_notice' => true,
                'accepted_cookie_notice_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
