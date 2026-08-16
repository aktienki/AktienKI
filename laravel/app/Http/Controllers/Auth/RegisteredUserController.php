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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Models\BetaInvitationLink;

class RegisteredUserController extends Controller
{
    private const BETA_TESTER_LIMIT = 20;

    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $invite = Str::upper(trim((string) $request->query('invite', '')));
        $invitation = $invite !== ''
            ? BetaInvitationLink::query()->where('token_hash', hash('sha256', $invite))->first()
            : null;

        return view('auth.register', [
            'invite' => $invite,
            'invitationValid' => $invitation?->isUsable() === true,
            'betaCodeRequired' => (bool) config('aktienki.beta.enabled', true)
                && ! (bool) config('aktienki.beta.phase_ended', false),
        ]);
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

            'country_code' => [
                'required',
                Rule::in(['DE','AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','US','CA','CH','GB','AU','CN','HK','JP']),
            ],

            'accept_disclaimer' => [
                'accepted',
            ],

            'risk_level' => [
                'required',
                Rule::in(['cautious', 'normal', 'opportunity_oriented']),
            ],

            'invite' => [
                Rule::requiredIf(
                    (bool) config('aktienki.beta.enabled', true)
                    && ! (bool) config('aktienki.beta.phase_ended', false)
                ),
                'string',
                'max:128',
            ],
        ]);

        $legalVersion = (string) config('legal.legal_version', '1.0-beta');
        $riskLevel = $request->string('risk_level')->toString();
        $betaCode = Str::upper(Str::random(4).'-'.Str::random(4));

        $inviteToken = Str::upper(trim((string) $request->input('invite', '')));

        $user = DB::transaction(function () use ($request, $legalVersion, $riskLevel, $betaCode, $inviteToken) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [24072026]);
            }

            $invitation = null;
            if ($inviteToken !== '') {
                $invitation = BetaInvitationLink::query()
                    ->where('token_hash', hash('sha256', $inviteToken))
                    ->lockForUpdate()
                    ->first();

                if (! $invitation || ! $invitation->isUsable()) {
                    throw ValidationException::withMessages([
                        'invite' => __('Dieser Einladungslink ist ungültig, abgelaufen oder bereits vollständig genutzt.'),
                    ]);
                }
            }

            // A valid invitation always grants beta status, even if the public
            // registration switch has meanwhile been disabled.
            $betaEnabled = (bool) config('aktienki.beta.enabled', true)
                && ! (bool) config('aktienki.beta.phase_ended', false)
                && $invitation !== null;

            $isBetaTester = DB::table('users')
                ->where('account_status', 'tester')
                ->count() < self::BETA_TESTER_LIMIT;

            $user = User::create([
                'name' => $request->name,
                'email' => strtolower($request->email),
                'password' => Hash::make($request->password),
                'preferences' => [
                    'country_code' => strtoupper((string) $request->input('country_code')),
                    'country_code_change_count' => 0,
                ],
                'account_status' => $betaEnabled ? 'pending_beta' : ($isBetaTester ? 'tester' : 'active'),
                'is_beta_tester' => $betaEnabled,

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
                    'beta_tester' => $betaEnabled ? [
                        'status' => 'tester',
                        'joined_at' => now()->toIso8601String(),
                        'permanent_pro_access' => true,
                    ] : null,
                    'beta_registration' => [
                        'status' => $betaEnabled ? 'pending_verification' : 'active',
                        'code_hash' => Hash::make($betaCode),
                        'code_encrypted' => Crypt::encryptString($betaCode),
                        'created_at' => now()->toIso8601String(),
                    ],
                    'beta_invitation' => $invitation ? [
                        'id' => $invitation->id,
                        'label' => $invitation->label,
                        'accepted_at' => now()->toIso8601String(),
                    ] : null,
                ],
            ]);

            if ($invitation) {
                $invitation->increment('uses_count');
                $invitation->forceFill(['last_used_at' => now()])->save();
            }

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
        Cache::forget('public.welcome.stats-v3');

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
