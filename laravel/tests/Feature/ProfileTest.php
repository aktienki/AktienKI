<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_user_can_configure_mobile_dashboard_cards(): void
    {
        $planId = DB::table('tariff_plans')->where('code', 'pro')->value('id');
        $user = User::factory()->create([
            'tariff_plan_id' => $planId, 'tariff_status' => 'active', 'tariff_ends_at' => now()->addYear(),
        ]);

        $this->actingAs($user)->get('/profile/mobile-view')->assertOk();
        $this->actingAs($user)->patch('/profile/mobile-view', [
            'cards' => ['personal', 'signal-cockpit', 'mobile-view'],
        ])->assertSessionHasNoErrors()->assertRedirect('/profile/mobile-view');

        $this->assertSame(
            ['personal', 'signal-cockpit', 'mobile-view'],
            data_get($user->refresh()->preferences, 'dashboard.mobile_cards')
        );
    }

    public function test_free_user_can_configure_mobile_dashboard_cards(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile/mobile-view')->assertOk();
        $this->actingAs($user)->patch('/profile/mobile-view', [
            'cards' => ['personal', 'market'],
        ])->assertSessionHasNoErrors()->assertRedirect('/profile/mobile-view');

        $this->assertSame(
            ['personal', 'market'],
            data_get($user->refresh()->preferences, 'dashboard.mobile_cards')
        );
    }

    public function test_user_can_reset_mobile_dashboard_cards(): void
    {
        $user = User::factory()->create([
            'preferences' => ['dashboard' => ['mobile_cards' => ['personal', 'signal-cockpit']]],
        ]);

        $this->actingAs($user)->delete('/profile/mobile-view')
            ->assertRedirect('/profile/mobile-view')
            ->assertSessionHas('status');

        $this->assertNull(data_get($user->refresh()->preferences, 'dashboard.mobile_cards'));
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_update_returns_to_the_page_that_opened_the_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'return_to' => '/predictions/heatmap?profit_factor_min=2',
        ])->assertSessionHasNoErrors()
            ->assertRedirect('/predictions/heatmap?profit_factor_min=2');
    }

    public function test_profile_update_does_not_redirect_to_an_external_site(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'return_to' => 'https://example.com/phishing',
        ])->assertSessionHasNoErrors()
            ->assertRedirect('/profile');
    }

    public function test_user_can_update_language_and_email_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => 'en',
            'email_service' => '1',
            'email_market_summary' => '1',
            'email_price_alerts' => '0',
            'email_product_updates' => '0',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');
        $response->assertSessionHas('locale', 'en');

        $preferences = $user->refresh()->preferences;
        $this->assertSame('en', $preferences['locale']);
        $this->assertTrue($preferences['email_service']);
        $this->assertTrue($preferences['email_market_summary']);
        $this->assertFalse($preferences['email_price_alerts']);
        $this->assertFalse($preferences['email_product_updates']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_profile_rejects_an_unsupported_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => 'invalid',
        ])->assertSessionHasErrors('locale');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
