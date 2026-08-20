@props(['class' => ''])

@auth
    @php
        $level = data_get(auth()->user()->meta, 'risk_profile.level', 'normal');
        $profiles = [
            'cautious' => [
                'label' => __('Vorsichtig'),
                'classes' => 'border-orange-400/25 bg-orange-400/10 text-orange-400',
            ],
            'conservative' => [
                'label' => __('Vorsichtig'),
                'classes' => 'border-orange-400/25 bg-orange-400/10 text-orange-400',
            ],
            'normal' => [
                'label' => __('Normal'),
                'classes' => 'border-teal-400/25 bg-teal-500/10 text-teal-500',
            ],
            'balanced' => [
                'label' => __('Normal'),
                'classes' => 'border-teal-400/25 bg-teal-500/10 text-teal-500',
            ],
            'opportunity_oriented' => [
                'label' => __('Chancenorientiert'),
                'classes' => 'border-rose-400/30 bg-rose-400/10 text-rose-300',
            ],
            'opportunity' => [
                'label' => __('Chancenorientiert'),
                'classes' => 'border-rose-400/30 bg-rose-400/10 text-rose-300',
            ],
            'aggressive' => [
                'label' => __('Chancenorientiert'),
                'classes' => 'border-rose-400/30 bg-rose-400/10 text-rose-300',
            ],
            'risk' => [
                'label' => __('Risk'),
                'classes' => 'border-red-500/35 bg-red-500/10 text-red-400',
            ],
        ];
        $profile = $profiles[$level] ?? $profiles['normal'];
    @endphp

    <a
        href="{{ route('profile.edit') }}"
        title="{{ __('Risikoprofil') }}: {{ $profile['label'] }}"
        class="ak-light-teal-badge {{ $class }} items-center gap-2 whitespace-nowrap rounded-xl border px-3 py-2 text-[11px] font-black transition hover:brightness-125 {{ $profile['classes'] }}"
    >
        <x-heroicon-o-shield-check class="h-4 w-4 shrink-0" />
        <span><span class="font-semibold opacity-70">{{ __('Risikoprofil') }}:</span> {{ $profile['label'] }}</span>
    </a>
@endauth
