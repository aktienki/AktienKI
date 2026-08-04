@props(['class' => ''])

@auth
    @php
        $level = data_get(auth()->user()->meta, 'risk_profile.level', 'normal');
        $profiles = [
            'cautious' => [
                'label' => __('Vorsichtig'),
                'classes' => 'border-cyan-400/25 bg-cyan-400/10 text-cyan-300',
            ],
            'conservative' => [
                'label' => __('Vorsichtig'),
                'classes' => 'border-cyan-400/25 bg-cyan-400/10 text-cyan-300',
            ],
            'normal' => [
                'label' => __('Normal'),
                'classes' => 'border-violet-400/25 bg-violet-500/10 text-violet-300',
            ],
            'balanced' => [
                'label' => __('Normal'),
                'classes' => 'border-violet-400/25 bg-violet-500/10 text-violet-300',
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
        ];
        $profile = $profiles[$level] ?? $profiles['normal'];
    @endphp

    <a
        href="{{ route('profile.edit') }}"
        title="{{ __('Risikoprofil') }}: {{ $profile['label'] }}"
        class="{{ $class }} items-center gap-2 whitespace-nowrap rounded-xl border px-3 py-2 text-[11px] font-black transition hover:brightness-125 {{ $profile['classes'] }}"
    >
        <x-heroicon-o-shield-check class="h-4 w-4 shrink-0" />
        <span><span class="font-semibold opacity-70">{{ __('Risikoprofil') }}:</span> {{ $profile['label'] }}</span>
    </a>
@endauth
