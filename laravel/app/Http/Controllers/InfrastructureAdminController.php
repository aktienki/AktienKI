<?php

namespace App\Http\Controllers;

use App\Services\MiniPcOperationsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InfrastructureAdminController extends Controller
{
    public function index(Request $request, MiniPcOperationsService $operations): View
    {
        $this->authorizeAdmin($request);

        return view('admin.infrastructure', ['status' => $operations->status()]);
    }

    public function action(Request $request, MiniPcOperationsService $operations): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'action' => ['required', 'in:restore_database_tunnel,restart_engine_worker,restart_walk_forward,toggle_training,set_cpu_reserve,restart_application_services,restart_remote_server'],
            'current_password' => [$request->input('action') === 'restart_remote_server' ? 'required' : 'nullable', 'current_password'],
            'reserve_cpus' => [$request->input('action') === 'set_cpu_reserve' ? 'required' : 'nullable', 'integer', 'in:0,1,2,4'],
        ]);
        Log::notice('Infrastructure maintenance action requested.', [
            'action' => $data['action'],
            'user_id' => $request->user()->getKey(),
            'email' => $request->user()->email,
            'ip' => $request->ip(),
        ]);
        $result = match ($data['action']) {
            'restore_database_tunnel' => $operations->restoreDatabaseTunnel(),
            'restart_engine_worker' => $operations->restartEngineWorker(),
            'restart_walk_forward' => $operations->restartWalkForward(),
            'toggle_training' => $operations->toggleTraining(),
            'set_cpu_reserve' => $operations->setCpuReserve((int) $data['reserve_cpus']),
            'restart_application_services' => $operations->restartApplicationServices(),
            'restart_remote_server' => $operations->restartRemoteServer(),
        };

        return redirect()->route('admin.infrastructure')
            ->with($result['successful'] ? 'status' : 'error', $result['message']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
    }
}
