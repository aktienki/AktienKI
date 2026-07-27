<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    private const BETA_TESTER_LIMIT = 50;

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:' . User::class,
            ],

            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
            ],

            'accept_disclaimer' => [
                'accepted',
            ],

            'accept_risk_notice' => [
                'accepted',
            ],

            'risk_level' => [
                'required',
                Rule::in(['cautious', 'normal', 'opportunity_oriented']),
            ],
        ]);

        $legalVersion = '1.0';
        $riskLevel = $request->string('risk_level')->toString();

        $user = DB::transaction(function () use ($request, $legalVersion, $riskLevel) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [24072026]);
            }

            $isBetaTester = DB::table('users')
                ->where('account_status', 'tester')
                ->count() < self::BETA_TESTER_LIMIT;

            $user = User::create([
                'name' => $request->name,
                'email' => strtolower($request->email),
                'password' => Hash::make($request->password),
                'account_status' => $isBetaTester ? 'tester' : 'active',

                'legal_accepted' => true,
                'legal_accepted_at' => now(),
                'legal_version' => $legalVersion,
                'legal_accept_ip' => $request->ip(),
                'legal_accept_user_agent' => $request->userAgent(),
                'accepted_terms' => true,
                'accepted_terms_at' => now(),
                'accepted_privacy' => true,
                'accepted_privacy_at' => now(),
                'accepted_risk_notice' => true,
                'accepted_risk_notice_at' => now(),
                'meta' => [
                    'risk_profile' => [
                        'version' => '1.0',
                        'completed_at' => now()->toIso8601String(),
                        'level' => $riskLevel,
                        'source' => 'registration_selection',
                    ],
                    'beta_tester' => $isBetaTester ? [
                        'status' => 'tester',
                        'joined_at' => now()->toIso8601String(),
                        'permanent_pro_access' => true,
                    ] : null,
                ],
            ]);

            $documents = DB::table('legal_documents')
                ->where('active', true)
                ->get();

            foreach ($documents as $document) {
                DB::table('user_legal_acceptances')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'legal_document_id' => $document->id,
                    ],
                    [
                        'accepted' => true,
                        'accepted_at' => now(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'meta' => json_encode([
                            'version' => $legalVersion,
                            'source' => 'registration',
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
