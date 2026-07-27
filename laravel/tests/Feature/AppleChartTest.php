<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppleChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('stocks.apple'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_apple_chart_with_demo_data(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('stocks.apple'));

        $response
            ->assertOk()
            ->assertSee('Apple Inc.')
            ->assertSee('AAPL')
            ->assertSee('apple-price-chart')
            ->assertSee(__('Demo-Daten – keine aktuellen Marktdaten vorhanden'));
    }

    public function test_apple_chart_is_available_in_english(): void
    {
        $user = User::factory()->create();

        $this->withSession(['locale' => 'en'])
            ->actingAs($user)
            ->get(route('stocks.apple'))
            ->assertOk()
            ->assertSee('Price performance')
            ->assertSee('This chart is for informational purposes only');
    }
}
