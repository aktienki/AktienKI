<?php

namespace Tests\Unit;

use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ProfileMobileViewTest extends TestCase
{
    public function test_legacy_mobile_selection_receives_the_new_champion_card(): void
    {
        $view = $this->mobileView([
            'dashboard' => ['mobile_cards' => ['market', 'signal-cockpit']],
        ]);

        $this->assertSame(
            ['champion', 'market', 'market-summary', 'schedule', 'signal-cockpit', 'personal', 'community'],
            $view->getData()['cards'],
        );
        $this->assertSame(['champion', 'market', 'signal-cockpit'], $view->getData()['selectedCards']);
    }

    public function test_current_mobile_selection_can_hide_the_champion_card(): void
    {
        $view = $this->mobileView([
            'dashboard' => [
                'mobile_cards' => ['market', 'signal-cockpit'],
                'mobile_cards_version' => 2,
            ],
        ]);

        $this->assertSame(['market', 'signal-cockpit'], $view->getData()['selectedCards']);
    }

    private function mobileView(array $preferences): \Illuminate\View\View
    {
        $user = new User(['preferences' => $preferences]);
        $request = Request::create('/profile/mobile-view');
        $request->setUserResolver(fn (): User => $user);

        return app(ProfileController::class)->mobileView($request);
    }
}
