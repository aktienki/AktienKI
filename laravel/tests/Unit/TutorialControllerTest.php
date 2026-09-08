<?php

namespace Tests\Unit;

use App\Http\Controllers\TutorialController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

final class TutorialControllerTest extends TestCase
{
    public function test_ajax_completion_is_persisted_and_returns_json_without_a_redirect(): void
    {
        $user = new class
        {
            public array $preferences = [];

            public bool $saved = false;

            public function forceFill(array $attributes): self
            {
                $this->preferences = $attributes['preferences'];

                return $this;
            }

            public function save(): void
            {
                $this->saved = true;
            }
        };

        $request = Request::create('/hilfe/abschliessen', 'POST');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(static fn () => $user);

        $response = (new TutorialController())->complete($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(['completed' => true], $response->getData(true));
        $this->assertTrue($user->saved);
        $this->assertNotEmpty(data_get($user->preferences, 'tutorial.completed_at'));
    }
}
