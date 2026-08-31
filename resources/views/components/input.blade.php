@props(['label', 'name', 'type' => 'text', 'hint' => null])

@php $id = $attributes->get('id', $name); @endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="block text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">
        {{ $label }}
    </label>

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes->merge([
            'class' => 'block h-11 w-full rounded-xl border bg-surface px-3.5 text-sm text-ink-strong
                        transition placeholder:text-ink-muted/70 focus:border-brand-500 focus:outline-none
                        focus:ring-4 focus:ring-brand-500/12 '
                .($errors->has($name) ? 'border-danger' : 'border-hairline'),
        ]) }}
    >

    @error($name)
        <p id="{{ $id }}-error" class="text-xs text-danger">{{ $message }}</p>
    @else
        @if ($hint)
            <p class="text-xs text-ink-muted">{{ $hint }}</p>
        @endif
    @enderror
</div>
