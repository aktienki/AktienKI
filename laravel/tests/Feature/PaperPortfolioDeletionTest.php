<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaperPortfolioDeletionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_can_permanently_delete_a_confirmed_paper_portfolio(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::query()->create([
            'user_id' => $user->id,
            'name' => 'Deletion test portfolio',
            'type' => 'paper',
            'currency' => 'EUR',
            'active' => true,
            'is_default' => false,
        ]);

        $response = $this->actingAs($user)->delete(route('depots.destroy', $portfolio), [
            'confirm_delete' => '1',
        ]);

        $response->assertRedirect(route('paper-depots.index'));
        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('portfolios', ['id' => $portfolio->id]);
    }

    public function test_deletion_still_requires_explicit_confirmation(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::query()->create([
            'user_id' => $user->id,
            'name' => 'Protected deletion test portfolio',
            'type' => 'paper',
            'currency' => 'EUR',
            'active' => true,
            'is_default' => false,
        ]);

        $response = $this->from(route('paper-depots.index'))
            ->actingAs($user)
            ->delete(route('depots.destroy', $portfolio));

        $response->assertRedirect(route('paper-depots.index'));
        $response->assertSessionHasErrors('confirm_delete');
        $this->assertDatabaseHas('portfolios', ['id' => $portfolio->id]);
    }
}
