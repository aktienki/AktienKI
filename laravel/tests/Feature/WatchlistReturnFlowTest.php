<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WatchlistReturnFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_watchlist_creation_returns_to_the_explicit_stock_list_origin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('watchlists.store'), [
            'name' => 'Screener selection',
            'return_to' => '/screener?signal=BUY',
        ]);

        $response->assertRedirect('/screener?signal=BUY');
        $response->assertSessionHas('status', 'watchlist-created');
        $this->assertDatabaseHas('watchlists', [
            'user_id' => $user->id,
            'name' => 'Screener selection',
        ]);
    }

    public function test_watchlist_creation_rejects_an_external_return_target(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('watchlists.store'), [
            'name' => 'Safe selection',
            'return_to' => '//example.com/redirect',
        ]);

        $response->assertRedirect(route('watchlists.index'));
    }
}
