<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_welcome_page_is_available_in_english(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get('/welcome');

        $response->assertOk();
        $response->assertSee('Stock analysis based on machine learning');
        $response->assertSee('One clear score.');
        $response->assertSee('Features at a glance');
        $response->assertSee('Your results');
    }

    public function test_animation_scenes_are_available_in_english(): void
    {
        $this->get('/scenes/machine-learning/en.svg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertSee('DATA STREAMS', false)
            ->assertSee('AI ENGINE', false);

        $this->get('/scenes/ai-score/en.svg')
            ->assertOk()
            ->assertSee('Daily AI Score', false)
            ->assertSee('MARKET', false);
    }

    public function test_pricing_page_links_back_to_welcome_page(): void
    {
        $this->get('/preise')
            ->assertOk()
            ->assertSee(route('welcome'), false)
            ->assertSee('Startseite');
    }

    public function test_registration_page_is_available_in_english(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get('/register');

        $response->assertOk();
        $response->assertSee('Create account');
        $response->assertSee('Analysis is not advice.');
        $response->assertSee('Risk notice');
        $response->assertSee('I acknowledge that AktienKI provides analysis and information tools only');
    }

    public function test_login_page_is_available_in_english(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get('/login');

        $response->assertOk();
        $response->assertSee('Welcome back');
        $response->assertSee('Sign in with your credentials.');
        $response->assertSee('Important notice');
        $response->assertSee('Keep me signed in');
    }

    public function test_features_page_is_available_in_both_languages(): void
    {
        $this->withSession(['locale' => 'de'])->get('/features')
            ->assertOk()
            ->assertSee('So funktioniert AktienKI')
            ->assertSee('Daten zusammenführen');

        $this->withSession(['locale' => 'en'])->get('/features')
            ->assertOk()
            ->assertSee('How AktienKI works')
            ->assertSee('Identify patterns with AI')
            ->assertSee('Risk indicators');
    }
}
