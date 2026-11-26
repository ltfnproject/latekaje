@php
    $photos = is_array($procurement->photo_paths ?? null) ? array_values($procurement->photo_paths) : [];
@endphp

@if (empty($photos))
    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada foto fisik kedatangan yang dilampirkan pada batch ini.</p>
@else
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($photos as $index => $path)
            @php
                $url = route('files.procurements.photo', ['procurement' => $procurement, 'index' => $index]);
            @endphp
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/60">
                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="block">
                    <img
                        src="{{ $url }}"
                        alt="Foto kedatangan #{{ $index + 1 }} — {{ $procurement->batch_code }}"
                        class="h-52 w-full object-cover transition hover:opacity-90"
                        loading="lazy"
                    />
                </a>
                <div class="flex items-center justify-between px-3 py-2 text-xs text-gray-600 dark:text-gray-300">
                    <span>Foto #{{ $index + 1 }}</span>
                    <a
                        href="{{ $url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-medium text-amber-600 hover:underline dark:text-amber-400"
                    >
                        Buka penuh &rarr;
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@endif
