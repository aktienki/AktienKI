<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The theme toggle used to only ever read/write localStorage - a saved
 * profile preference never made it back into a fresh page load at all, so a
 * user who chose dark mode on one device still saw light mode on another.
 * preference-head.blade.php's inline (pre-Vite) bootstrap script now syncs
 * the authoritative, saved preference into localStorage before anything
 * else reads it.
 */
final class ThemePreferenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_users_saved_dark_theme_is_synced_into_the_page_on_load(): void
    {
        $user = User::factory()->create(['preferences' => ['theme' => 'dark']]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $this->assertStringContainsString("const serverTheme = 'dark'", $response->getContent());
    }

    public function test_a_guest_gets_no_server_theme_and_falls_back_to_localstorage(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertStringContainsString('const serverTheme = null', $response->getContent());
    }

    public function test_an_invalid_stored_theme_value_is_ignored(): void
    {
        $user = User::factory()->create(['preferences' => ['theme' => 'not-a-real-theme']]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $this->assertStringContainsString('const serverTheme = null', $response->getContent());
    }

    public function test_the_theme_toggle_is_enabled_in_the_main_topbar(): void
    {
        $topbar = (string) file_get_contents(resource_path('views/components/app-topbar.blade.php'));

        $this->assertStringContainsString('<x-preference-controls :show-theme="true"', $topbar);
    }
}
