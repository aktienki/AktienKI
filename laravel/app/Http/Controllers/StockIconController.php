<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Services\StockIconService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockIconController extends Controller
{
    public function __invoke(Instrument $instrument, StockIconService $icons): BinaryFileResponse|Response
    {
        // HTTP requests must never wait for external company websites. Missing
        // icons are populated separately and the UI immediately uses initials.
        $path = $icons->findCached($instrument);

        if (! $path) {
            return response('', 404, [
                'Cache-Control' => 'public, max-age=604800, immutable',
            ]);
        }

        return response()
            ->file($path)
            ->setCache([
                'public' => true,
                'max_age' => 604800,
                's_maxage' => 604800,
                'immutable' => true,
            ]);
    }
}
