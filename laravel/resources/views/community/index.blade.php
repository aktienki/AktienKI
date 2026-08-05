<x-app-layout>
    @php
        $topics = [
            'general' => [__('Allgemein'), 'heroicon-o-chat-bubble-left-right'],
            'markets' => [__('Märkte'), 'heroicon-o-globe-europe-africa'],
            'stocks' => [__('Aktien'), 'heroicon-o-chart-bar-square'],
            'strategies' => [__('Strategien'), 'heroicon-o-adjustments-horizontal'],
        ];
    @endphp

    <div id="community-page" class="ak-body h-[calc(100dvh-73px)] overflow-hidden">
        <header class="community-header border-b border-[var(--ak-border)] bg-[var(--ak-bg)]/95 py-3 backdrop-blur-xl">
            <div class="ak-container flex items-center justify-between gap-4">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[.2em] text-orange-400">aKI Community</p>
                    <h1 class="mt-0.5 text-2xl font-black text-[var(--ak-text)]">{{ __('Austausch unter Anlegern') }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    <span class="community-stat rounded-lg border border-orange-400/20 bg-orange-400/[.06] px-3 py-2 text-xs text-[var(--ak-muted)]"><b class="text-orange-400">{{ $memberCount }}</b> {{ __('aktive Autoren') }}</span>
                    <span class="community-stat rounded-lg border border-orange-400/20 bg-orange-400/[.06] px-3 py-2 text-xs text-[var(--ak-muted)]"><b class="text-orange-400">{{ $postCount }}</b> {{ __('Beiträge') }}</span>
                </div>
            </div>
        </header>

        <main class="ak-container grid h-[calc(100%-70px)] min-h-0 gap-4 py-4 xl:grid-cols-[340px_minmax(0,1fr)]">
            <aside class="flex min-h-0 flex-col gap-3">
                <section class="ak-card ak-dashboard-card border-orange-400/35 p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="grid h-9 w-9 place-items-center rounded-lg border border-amber-300/30 bg-amber-300/10 text-amber-300"><x-heroicon-o-identification class="h-5 w-5" /></span>
                        <div><p class="text-[9px] font-black uppercase tracking-wider text-orange-400">{{ __('Deine Identität') }}</p><h2 class="text-base font-black text-[var(--ak-text)]">{{ __('Community-Alias') }}</h2></div>
                    </div>
                    <p class="mb-3 text-xs leading-5 text-[var(--ak-muted)]">{{ __('In der Community werden weder dein echter Name noch deine E-Mail-Adresse angezeigt.') }}</p>
                    <form method="POST" action="{{ route('community.alias.update') }}" class="flex gap-2">
                        @csrf @method('PATCH')
                        <label class="min-w-0 flex-1">
                            <span class="sr-only">{{ __('Alias') }}</span>
                            <input name="community_alias" value="{{ old('community_alias', auth()->user()->community_alias) }}" maxlength="24" placeholder="{{ __('z. B. markt_fuchs') }}" class="ak-input h-10 w-full rounded-lg px-3 text-sm" required>
                        </label>
                        <button class="rounded-lg border border-orange-400/35 bg-orange-400/15 px-3 text-xs font-black text-orange-400 hover:bg-orange-400/25">{{ __('Speichern') }}</button>
                    </form>
                    @error('community_alias')<p class="mt-2 text-xs text-rose-300">{{ $message }}</p>@enderror
                </section>

                <section class="ak-card ak-dashboard-card min-h-0 flex-1 border-orange-400/25 p-4">
                    <div class="mb-3 flex items-center justify-between"><h2 class="text-base font-black text-[var(--ak-text)]">{{ __('Neuer Beitrag') }}</h2><x-heroicon-o-pencil-square class="h-5 w-5 text-orange-400" /></div>
                    @if (auth()->user()->community_alias)
                        <form method="POST" action="{{ route('community.posts.store') }}" class="flex h-[calc(100%-32px)] flex-col gap-2.5">
                            @csrf
                            <select name="topic" class="ak-input h-10 rounded-lg px-3 text-sm" required>
                                @foreach ($topics as $key => [$label])<option value="{{ $key }}" @selected(old('topic') === $key)>{{ $label }}</option>@endforeach
                            </select>
                            <input name="title" value="{{ old('title') }}" maxlength="100" placeholder="{{ __('Überschrift') }}" class="ak-input h-10 rounded-lg px-3 text-sm" required>
                            <textarea name="body" maxlength="2000" placeholder="{{ __('Teile deine Beobachtung oder starte eine Diskussion …') }}" class="ak-input min-h-[110px] flex-1 resize-none rounded-lg p-3 text-sm leading-5" required>{{ old('body') }}</textarea>
                            @error('title')<p class="text-xs text-rose-300">{{ $message }}</p>@enderror
                            @error('body')<p class="text-xs text-rose-300">{{ $message }}</p>@enderror
                            <button class="h-10 rounded-lg border border-orange-400/35 bg-orange-400/20 text-sm font-black text-orange-400 hover:bg-orange-400/30">{{ __('Veröffentlichen') }}</button>
                        </form>
                    @else
                        <div class="grid h-[calc(100%-32px)] place-items-center rounded-xl border border-dashed border-amber-300/25 bg-amber-300/[.04] p-5 text-center">
                            <div><x-heroicon-o-lock-closed class="mx-auto h-7 w-7 text-amber-300" /><p class="mt-2 text-sm font-bold text-[var(--ak-text)]">{{ __('Alias erforderlich') }}</p><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Speichere zuerst deinen Alias, um Beiträge zu verfassen.') }}</p></div>
                        </div>
                    @endif
                </section>
            </aside>

            <section class="ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-orange-400/30">
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-[var(--ak-border)] px-4 py-3">
                    <div><p class="text-[9px] font-black uppercase tracking-wider text-orange-400">{{ __('Community Feed') }}</p><h2 class="text-lg font-black text-[var(--ak-text)]">{{ __('Aktuelle Diskussionen') }}</h2></div>
                    <nav class="flex gap-1">
                        <a href="{{ route('community.index') }}" class="community-topic rounded-md px-2.5 py-1.5 text-[10px] font-black {{ !$activeTopic ? 'bg-orange-400 text-slate-950' : 'bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]' }}">{{ __('Alle') }}</a>
                        @foreach ($topics as $key => [$label])
                            <a href="{{ route('community.index', ['topic' => $key]) }}" class="community-topic rounded-md px-2.5 py-1.5 text-[10px] font-black {{ $activeTopic === $key ? 'bg-orange-400 text-slate-950' : 'bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] hover:text-orange-400' }}">{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>

                @if (session('community_status'))<div class="mx-4 mt-3 rounded-lg border border-orange-400/25 bg-orange-400/10 px-3 py-2 text-xs font-bold text-orange-400">{{ session('community_status') }}</div>@endif

                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
                    @forelse ($posts as $post)
                        <article class="community-post rounded-xl border border-orange-400/15 bg-orange-400/[.035] p-4 shadow-[0_8px_24px_rgba(2,6,23,.10)]">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-sm font-black uppercase text-orange-400">{{ mb_substr($post->user->community_alias ?? '?', 0, 1) }}</span>
                                    <div class="min-w-0"><div class="flex items-center gap-2"><b class="truncate text-sm text-orange-400">{{ '@'.($post->user->community_alias ?? __('gelöscht')) }}</b><span class="rounded bg-amber-300/10 px-1.5 py-0.5 text-[8px] font-black text-amber-300">{{ $topics[$post->topic][0] ?? __('Allgemein') }}</span></div><time class="text-[10px] text-[var(--ak-muted)]">{{ $post->created_at->diffForHumans() }}</time></div>
                                </div>
                                @if ($post->user_id === auth()->id() || auth()->user()->is_admin)
                                    <form method="POST" action="{{ route('community.posts.destroy', $post) }}" onsubmit="return confirm('{{ __('Beitrag wirklich löschen?') }}')">@csrf @method('DELETE')<button class="text-slate-500 hover:text-rose-300" title="{{ __('Löschen') }}"><x-heroicon-o-trash class="h-4 w-4" /></button></form>
                                @endif
                            </div>
                            <h3 class="mt-3 text-base font-black text-[var(--ak-text)]">{{ $post->title }}</h3>
                            <p class="mt-1.5 whitespace-pre-line text-sm leading-5 text-[var(--ak-muted)]">{{ $post->body }}</p>
                        </article>
                    @empty
                        <div class="grid h-full place-items-center text-center"><div><x-heroicon-o-chat-bubble-left-right class="mx-auto h-9 w-9 text-orange-400/50" /><p class="mt-3 font-bold text-[var(--ak-text)]">{{ __('Noch keine Beiträge') }}</p><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Starte die erste Diskussion in diesem Bereich.') }}</p></div></div>
                    @endforelse
                </div>
                @if ($posts->hasPages())<div class="shrink-0 border-t border-[var(--ak-border)] px-4 py-2">{{ $posts->links() }}</div>@endif
            </section>
        </main>
    </div>
</x-app-layout>
