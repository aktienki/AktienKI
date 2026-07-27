<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_page_can_be_rendered(): void
    {
        $this->get(route('reviews.index'))
            ->assertOk()
            ->assertSee(__('Bewertungen zu AktienKI.com'));
    }

    public function test_a_review_can_be_submitted_and_is_visible(): void
    {
        $user = User::factory()->create(['name' => 'Test Nutzer']);

        $response = $this->actingAs($user)->post(route('reviews.store'), [
            'rating' => 5,
            'title' => 'Sehr hilfreich',
            'comment' => 'Die Analysen sind klar und sehr verständlich aufgebaut.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reviews', [
            'name' => 'Test Nutzer',
            'rating' => 5,
            'is_published' => true,
        ]);

        $this->get(route('reviews.index'))
            ->assertSee('Sehr hilfreich')
            ->assertSee('Die Analysen sind klar');
    }

    public function test_review_submission_is_validated(): void
    {
        $this->actingAs(User::factory()->create())->post(route('reviews.store'), [
            'rating' => 6,
            'comment' => 'kurz',
        ])->assertSessionHasErrors(['rating', 'comment']);

        $this->assertSame(0, Review::count());
    }

    public function test_guests_cannot_submit_a_review(): void
    {
        $this->post(route('reviews.store'), [
            'rating' => 5,
            'comment' => 'Eine ausreichend lange Bewertung für diesen Test.',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, Review::count());
    }
}
