<?php

namespace App\Http\Controllers;

final class NvidiaLandingController extends KlaLandingController
{
    protected string $symbol = 'NVDA';
    protected string $view = 'landing.nvidia';
    protected string $cachePrefix = 'nvidia';
}
