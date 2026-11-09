@php
    $title = trim((string) ($props['title'] ?? '')) !== '' ? (string) $props['title'] : (string) __('pagebuilder.contact_form_title');
    $button = trim((string) ($props['button'] ?? '')) !== '' ? (string) $props['button'] : (string) __('common.send');
    $subject = trim((string) ($props['subject'] ?? ''));
    $enviado = session('enviado');

    // The error bag only exists in web request scope (shared by middleware).
    // This block can also be rendered inside cached template contexts
    // (header/footer templates, queue), where $errors is NOT defined — guard.
    $__bag = ($errors ?? null) instanceof \Illuminate\Support\ViewErrorBag ? $errors->getBag('default') : null;
    $__invalid = fn (string $key): string => $__bag !== null && $__bag->has($key) ? ' is-invalid' : '';
    $__msg = fn (string $key): string => $__invalid($key) !== '' ? (string) $__bag->first($key) : '';
@endphp
<section
    id="{{ $node->id }}"
    class="pb-contact-form {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>
    <div class="container py-5">
        <div class="mx-auto" style="max-width: 640px">
            <h3 class="h4 mb-4">{{ $title }}</h3>

            @if ($enviado === 'contato')
                <div class="alert alert-success" role="status">{{ __('site.form_sent') }}</div>
            @elseif ($enviado === 'contato_erro')
                <div class="alert alert-danger" role="alert">{{ __('site.form_failed') }}</div>
            @endif

            <form method="POST" action="{{ route('site.contato.enviar') }}" novalidate>
                @csrf

                {{-- Honeypot: humans never fill this; bots do, and the submission is discarded. --}}
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true"
                       style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden">

                @if ($subject !== '')
                    <input type="hidden" name="assunto" value="{{ $subject }}">
                @endif

                <div class="mb-3">
                    <label class="form-label" for="contato-nome-{{ $node->id }}">{{ __('site.form_name') }}</label>
                    <input type="text" id="contato-nome-{{ $node->id }}" name="nome" maxlength="120" required
                           value="{{ (string) old('nome') }}"
                           class="form-control{{ $__invalid('nome') }}">
                    @if ($__msg('nome') !== '') <div class="invalid-feedback">{{ $__msg('nome') }}</div> @endif
                </div>

                <div class="mb-3">
                    <label class="form-label" for="contato-email-{{ $node->id }}">{{ __('site.form_email') }}</label>
                    <input type="email" id="contato-email-{{ $node->id }}" name="email" maxlength="190" required
                           value="{{ (string) old('email') }}"
                           class="form-control{{ $__invalid('email') }}">
                    @if ($__msg('email') !== '') <div class="invalid-feedback">{{ $__msg('email') }}</div> @endif
                </div>

                <div class="mb-3">
                    <label class="form-label" for="contato-mensagem-{{ $node->id }}">{{ __('site.form_message') }}</label>
                    <textarea id="contato-mensagem-{{ $node->id }}" name="mensagem" rows="5" maxlength="5000" required
                              class="form-control{{ $__invalid('mensagem') }}">{{ (string) old('mensagem') }}</textarea>
                    @if ($__msg('mensagem') !== '') <div class="invalid-feedback">{{ $__msg('mensagem') }}</div> @endif
                </div>

                <button type="submit" class="btn btn-primary">{{ $button }}</button>
            </form>
        </div>
    </div>
</section>
