@props(['platform', 'class' => 'size-5'])

{{-- Lambang platform tanpa latar. Dipisah dari x-platform-badge supaya lencana
     dan tombol hubungkan memakai gambar yang sama, bukan dua salinan SVG. --}}
@if ($platform === 'instagram')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
         stroke-linecap="round" stroke-linejoin="round"
         class="{{ $class }} shrink-0" aria-hidden="true">
        <rect x="3" y="3" width="18" height="18" rx="5"/>
        <circle cx="12" cy="12" r="4"/>
        <circle cx="17.4" cy="6.6" r="1.1" fill="currentColor" stroke="none"/>
    </svg>
@elseif ($platform === 'facebook')
    <svg viewBox="0 0 24 24" fill="currentColor" class="{{ $class }} shrink-0" aria-hidden="true">
        <path d="M14.2 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5H17.4V3.6c-.29-.04-1.27-.12-2.42-.12-2.4 0-4.03 1.46-4.03 4.15V9.9H8.24V13h2.71v8z"/>
    </svg>
@else
    <span class="font-display text-xs font-bold uppercase">{{ substr((string) $platform, 0, 2) }}</span>
@endif
