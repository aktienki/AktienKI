<?php

namespace App\Http\Middleware;

use App\Services\PlanAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePlanLevel
{
    public function __construct(private readonly PlanAccessService $access) {}

    public function handle(Request $request, Closure $next, string $level): Response
    {
        if ($request->user() && $this->access->allowsTariff($request->user(), $level)) return $next($request);

        $requiredPlan = match (strtolower($level)) {
            'premium' => 'Premium',
            'pro' => 'Pro',
            default => 'Plus',
        };

        return redirect()->route('pricing')->with('status', __('Diese Speicherfunktion ist im :plan-Tarif verfügbar.', ['plan' => $requiredPlan]));
    }
}
