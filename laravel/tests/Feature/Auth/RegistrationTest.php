<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'country_code' => 'DE',
            'accept_disclaimer' => '1',
            'risk_level' => 'normal',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame('normal', auth()->user()->meta['risk_profile']['level']);
        $this->assertSame('DE', auth()->user()->preferences['country_code']);
    }

    public function test_registration_requires_disclaimer_acceptance(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('accept_disclaimer');
        $this->assertGuest();
    }

    public function test_registration_requires_a_complete_risk_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'accept_disclaimer' => '1',
        ]);

        $response->assertSessionHasErrors([
            'risk_level',
        ]);
        $this->assertGuest();
    }

}
