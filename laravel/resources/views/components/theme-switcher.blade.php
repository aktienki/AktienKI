@php
    $theme = $activeUiTheme ?? auth()->user()?->ui_theme ?? 'purple';
@endphp

<form
    method="POST"
    action="{{ url('/settings/theme') }}"
    class="ak-theme-switcher"
    aria-label="Design auswählen"
>
    @csrf
    @method('PATCH')

    @foreach(\App\Enums\UiTheme::cases() as $option)
        <button
            type="submit"
            name="ui_theme"
            value="{{ $option->value }}"
            class="ak-theme-option {{ $theme === $option->value ? 'is-active' : '' }}"
            data-theme-value="{{ $option->value }}"
            title="{{ $option->label() }}"
            aria-label="{{ $option->label() }}"
            aria-pressed="{{ $theme === $option->value ? 'true' : 'false' }}"
        ></button>
    @endforeach
</form>
