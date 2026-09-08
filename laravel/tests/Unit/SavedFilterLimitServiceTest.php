<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\SavedFilterLimitService;
use Tests\TestCase;

final class SavedFilterLimitServiceTest extends TestCase
{
    public function test_admin_flag_receives_fifty_saved_strategies(): void
    {
        $user = new User(['is_admin' => true]);

        $this->assertSame(50, app(SavedFilterLimitService::class)->limitFor($user));
    }

    public function test_admin_role_receives_fifty_saved_strategies(): void
    {
        $user = new User(['role' => 'ADMIN']);

        $this->assertSame(50, app(SavedFilterLimitService::class)->limitFor($user));
    }
}
