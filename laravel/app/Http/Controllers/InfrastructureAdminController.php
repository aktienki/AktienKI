<?php

namespace App\Http\Controllers;

use App\Services\MacMiniOperationsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InfrastructureAdminController extends Controller
{
    public function index(Request $request, MacMiniOperationsService $operations): View
    {
        $this->authorizeAdmin($request);

        return view('admin.infrastructure', ['status' => $operations->status()]);
    }

    public function action(Request $request, MacMiniOperationsService $operations): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'action' => ['required', 'in:restart_application_services,restart_remote_server'],
            'current_password' => [$request->input('action') === 'restart_remote_server' ? 'required' : 'nullable', 'current_password'],
        ]);
        Log::notice('Infrastructure maintenance action requested.', [
            'action' => $data['action'],
            'user_id' => $request->user()->getKey(),
            'email' => $request->user()->email,
            'ip' => $request->ip(),
        ]);
        $result = match ($data['action']) {
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
