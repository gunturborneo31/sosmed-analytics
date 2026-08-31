@foreach (['success' => 'success', 'error' => 'danger', 'status' => 'brand'] as $key => $tone)
    @if (session($key))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition.duration.300ms
            class="mb-5 flex items-start justify-between gap-4 rounded-xl border px-4 py-3 text-sm
                   @class([
                       'border-success/25 bg-success/10 text-success' => $tone === 'success',
                       'border-danger/25 bg-danger/10 text-danger' => $tone === 'danger',
                       'border-brand-100 bg-brand-50 text-brand-700' => $tone === 'brand',
                   ])"
            role="status"
        >
            <span>{{ session($key) }}</span>
            <button type="button" @click="show = false" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Tutup">&times;</button>
        </div>
    @endif
@endforeach
