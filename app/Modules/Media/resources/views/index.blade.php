@extends('core::admin.layout')

@section('title', __('nav.media'))

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">{{ __('media.index_title') }}</h2>
            <p class="text-sm text-slate-500">{{ __('media.index_subtitle') }}</p>
        </div>
        <form method="GET" action="{{ route('admin.midia.index') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('media.search_placeholder') }}"
                   class="w-64 rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
            <button type="submit" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">{{ __('pages.search') }}</button>
        </form>
    </div>

    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-semibold">{{ __('media.upload_title') }}</h3>
        <p class="mb-3 text-sm text-slate-500">
            {{ __('media.upload_help', [
                'maxMB' => (int) floor(config('cms.media.max_upload_bytes') / 1048576),
                'maxVidMB' => (int) floor(config('cms.media.max_video_bytes') / 1048576),
                'maxDim' => config('cms.media.max_dimension'),
            ]) }}
        </p>
        <form method="POST" action="{{ route('admin.midia.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
            @csrf
            <input type="file" name="files[]" multiple required
                   accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,video/ogg"
                   class="text-sm file:mr-3 file:rounded file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500">
            <button type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                {{ __('media.upload') }}
            </button>
        </form>
        @error('files')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('files.*')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @if ($media->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            {{ __('media.empty') }}
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($media as $item)
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    @if ($item->isVideo())
                        <button type="button" data-medialb="{{ $item->url() }}" data-medialb-name="{{ $item->filename }}"
                                aria-label="{{ __('media.view_video') }}"
                                class="relative block w-full cursor-zoom-in bg-slate-900 text-left">
                            <video src="{{ $item->url() }}#t=0.5" preload="metadata" muted playsinline aria-hidden="true"
                                   class="pointer-events-none h-36 w-full object-contain">
                                {{ __('media.video_not_supported') }}
                            </video>
                            <span class="absolute left-2 top-2 rounded bg-slate-900/80 px-2 py-0.5 text-xs font-semibold text-white">{{ __('media.badge_video') }}</span>
                            <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                <span class="grid h-11 w-11 place-items-center rounded-full border border-amber-400/60 bg-slate-900/70 text-lg text-white">▶</span>
                            </span>
                        </button>
                    @else
                        <a href="{{ $item->url() }}" target="_blank" rel="noopener" class="relative block bg-slate-900">
                            <img src="{{ $item->url() }}" alt="{{ $item->alt ?? $item->filename }}" loading="lazy"
                                 class="h-36 w-full object-cover">
                        </a>
                    @endif
                    <div class="p-3">
                        <p class="truncate text-sm font-medium" title="{{ $item->filename }}">{{ $item->filename }}</p>
                        <p class="text-xs text-slate-400">
                            {{ $item->isVideo()
                                ? __('media.meta_video', ['size' => number_format($item->size / 1048576, 1, ',', '.')])
                                : __('media.meta_image', ['width' => $item->width, 'height' => $item->height, 'size' => number_format($item->size / 1024, 0, ',', '.')]) }}
                        </p>
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <a href="{{ route('admin.midia.edit', $item) }}" class="font-medium text-primary-600 hover:text-primary-500">{{ __('common.edit') }}</a>
                            <form method="POST" action="{{ route('admin.midia.destroy', $item) }}"
                                  onsubmit="return confirm('{{ __('media.delete_confirm', ['name' => $item->filename]) }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-red-600 hover:text-red-500">{{ __('common.delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $media->links() }}
        </div>

        @if ($media->contains(fn ($m) => $m->isVideo()))
            {{-- Lightbox do painel: play/mídia só no modal (o tile é poster). --}}
            <div id="media-lightbox" hidden role="dialog" aria-modal="true" aria-label="{{ __('media.view_video') }}"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4 sm:p-10">
                <button type="button" data-lb-close aria-label="{{ __('common.close') }}"
                        class="absolute right-3 top-3 grid h-11 w-11 place-items-center rounded-full border border-slate-500 bg-slate-900/70 text-white hover:bg-primary-600">✕</button>
                <video controls playsinline autoplay class="max-h-[80vh] w-auto max-w-full rounded-xl bg-black"></video>
                <p class="absolute bottom-4 left-1/2 -translate-x-1/2 truncate px-4 text-sm text-slate-300" data-lb-name></p>
            </div>

            <script>
                (function () {
                    var box = document.getElementById('media-lightbox');
                    if (!box || !box.querySelector('video')) { return; }
                    var vid = box.querySelector('video');
                    var nameEl = box.querySelector('[data-lb-name]');
                    var last = null;

                    function open(src, name) {
                        last = document.activeElement;
                        nameEl.textContent = name || '';
                        vid.src = src;
                        box.hidden = false;
                        var p = vid.play();
                        if (p && p.catch) { p.catch(function () {}); }
                        box.querySelector('[data-lb-close]').focus();
                    }

                    function close() {
                        box.hidden = true;
                        vid.pause();
                        vid.removeAttribute('src');
                        vid.load();
                        if (last && last.focus) { last.focus(); }
                    }

                    document.querySelectorAll('[data-medialb]').forEach(function (tile) {
                        tile.addEventListener('click', function (ev) {
                            ev.preventDefault();
                            open(tile.getAttribute('data-medialb'), tile.getAttribute('data-medialb-name'));
                        });
                    });

                    box.querySelector('[data-lb-close]').addEventListener('click', close);
                    box.addEventListener('click', function (ev) { if (ev.target === box) { close(); } });
                    document.addEventListener('keydown', function (ev) {
                        if (!box.hidden && ev.key === 'Escape') { close(); }
                    });
                })();
            </script>
        @endif
    @endif
@endsection
