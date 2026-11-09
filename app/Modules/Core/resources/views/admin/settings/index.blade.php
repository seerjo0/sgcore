@extends('core::admin.layout')

@section('title', __('nav.settings'))

@section('content')
    <div class="mb-4">
        <h2 class="text-xl font-semibold">{{ __('nav.settings') }}</h2>
        <p class="text-sm text-slate-500">{{ __('settings.subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('admin.configuracoes.update') }}"
          class="max-w-3xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <h3 class="text-base font-semibold text-slate-800">{{ __('settings.admin') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('settings.admin_help') }}</p>

        <fieldset class="mt-5">
            <legend class="text-sm font-medium text-slate-700">{{ __('settings.mode') }}</legend>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                @foreach ($modes as $key => $label)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-4 hover:border-slate-300 has-[:checked]:border-primary-600 has-[:checked]:ring-1 has-[:checked]:ring-primary-600">
                        <input type="radio" name="mode" value="{{ $key }}" @checked(old('mode', $currentMode) === $key) required
                               class="h-4 w-4 border-slate-300 text-primary-600 focus:ring-primary-500">
                        @if ($key === 'escuro')
                            <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                            </svg>
                        @else
                            <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                            </svg>
                        @endif
                        <span class="text-sm font-medium text-slate-700">{{ __("settings.mode_{$key}") }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="mt-6">
            <legend class="text-sm font-medium text-slate-700">{{ __('settings.color') }}</legend>
            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                @foreach ($colors as $key => $color)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-4 hover:border-slate-300 has-[:checked]:border-primary-600 has-[:checked]:ring-1 has-[:checked]:ring-primary-600">
                        <input type="radio" name="color" value="{{ $key }}" @checked(old('color', $currentColor) === $key) required
                               class="h-4 w-4 border-slate-300 text-primary-600 focus:ring-primary-500">
                        <span class="h-5 w-5 shrink-0 rounded-full border border-black/10" style="background-color: {{ $color['hex'] }}"></span>
                        <span class="text-sm font-medium text-slate-700">{{ __("settings.color_{$key}") }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="mt-6">
            <legend class="text-sm font-medium text-slate-700">{{ __('settings.path') }}</legend>
            <div class="mt-2 flex items-center gap-2">
                <span class="rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-500">/</span>
                <input type="text" name="admin_path" maxlength="30" pattern="[a-z0-9\-]+" title="{{ __('settings.path_pattern') }}"
                       value="{{ old('admin_path', $adminPath) }}"
                       class="w-56 rounded-r border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>
            <p class="mt-2 text-xs text-slate-500">
                {{ __('settings.path_help_before') }}
                <code class="rounded bg-slate-100 px-1">admin</code>,
                <code class="rounded bg-slate-100 px-1">backend</code>,
                <code class="rounded bg-slate-100 px-1">administrator</code>.
                {{ __('settings.path_help_after') }}
            </p>
            @error('admin_path')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </fieldset>

        <hr class="my-8 border-slate-200">

        <h3 class="text-base font-semibold text-slate-800">{{ __('settings.site') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('settings.site_help') }}</p>

        <div class="mt-5 grid gap-5">
            <div>
                <label for="site_name" class="block text-sm font-medium text-slate-700">{{ __('settings.site_name') }}</label>
                <input type="text" id="site_name" name="site_name" maxlength="100" required
                       value="{{ old('site_name', $siteName) }}"
                       class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @error('site_name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('settings.logo') }}</label>
                <input type="hidden" name="site_logo" value="{{ old('site_logo', $siteLogoId) }}">
                <div class="mt-1 flex items-center gap-3">
                    <img id="site-logo-preview" src="{{ $siteLogoUrl }}" alt=""
                         class="h-16 w-16 rounded border border-slate-200 object-cover {{ $siteLogoUrl !== '' ? '' : 'hidden' }}">
                    <button type="button" onclick="pickSettingsImage('site_logo', 'site-logo-preview')"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">
                        {{ __('settings.choose_library') }}
                    </button>
                    <button type="button" onclick="clearSettingsImage('site_logo', 'site-logo-preview')"
                            class="text-sm text-slate-500 hover:text-red-600">{{ __('settings.remove') }}</button>
                </div>
                @error('site_logo')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('settings.favicon') }}</label>
                <input type="hidden" name="site_favicon" value="{{ old('site_favicon', $faviconId) }}">
                <div class="mt-1 flex items-center gap-3">
                    <img id="favicon-preview" src="{{ $faviconUrl }}" alt=""
                         class="h-10 w-10 rounded border border-slate-200 object-cover {{ $faviconUrl !== '' ? '' : 'hidden' }}">
                    <button type="button" onclick="pickSettingsImage('site_favicon', 'favicon-preview')"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">
                        {{ __('settings.choose_library') }}
                    </button>
                    <button type="button" onclick="clearSettingsImage('site_favicon', 'favicon-preview')"
                            class="text-sm text-slate-500 hover:text-red-600">{{ __('settings.remove') }}</button>
                </div>
                @error('site_favicon')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <hr class="my-8 border-slate-200">

        <h3 class="text-base font-semibold text-slate-800">{{ __('settings.locale') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('settings.locale_help') }}</p>

        <div class="mt-5">
            <select name="site_locale"
                    class="w-64 rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @foreach ($locales as $code => $label)
                    <option value="{{ $code }}" @selected(old('locale', $currentLocale) === $code)>{{ $label }}</option>
                @endforeach
            </select>
            @error('locale')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <hr class="my-8 border-slate-200">

        <h3 class="text-base font-semibold text-slate-800">{{ __('settings.seo') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('settings.seo_help') }}</p>

        <div class="mt-5 grid gap-5">
            <div>
                <label for="seo_title" class="block text-sm font-medium text-slate-700">{{ __('settings.seo_title') }}</label>
                <input type="text" id="seo_title" name="seo_title" maxlength="255"
                       value="{{ old('seo_title', $seoTitle) }}"
                       class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @error('seo_title')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="seo_description" class="block text-sm font-medium text-slate-700">{{ __('settings.seo_description') }}</label>
                <textarea id="seo_description" name="seo_description" rows="3" maxlength="300"
                          class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('seo_description', $seoDescription) }}</textarea>
                @error('seo_description')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('settings.og_image') }}</label>
                <input type="hidden" name="seo_og_image" value="{{ old('seo_og_image', $seoOgImageId) }}">
                <div class="mt-1 flex items-center gap-3">
                    <img id="seo-og-preview" src="{{ $seoOgImageUrl }}" alt=""
                         class="h-16 w-28 rounded border border-slate-200 object-cover {{ $seoOgImageUrl !== '' ? '' : 'hidden' }}">
                    <button type="button" onclick="pickSettingsImage('seo_og_image', 'seo-og-preview')"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">
                        {{ __('settings.choose_library') }}
                    </button>
                    <button type="button" onclick="clearSettingsImage('seo_og_image', 'seo-og-preview')"
                            class="text-sm text-slate-500 hover:text-red-600">{{ __('settings.remove') }}</button>
                </div>
                @error('seo_og_image')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <hr class="my-8 border-slate-200">

        <h3 class="text-base font-semibold text-slate-800">{{ __('settings.mail') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('settings.mail_help') }}</p>

        <p class="mt-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium
                  {{ $mailReady ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
            <span class="h-2 w-2 rounded-full {{ $mailReady ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
            {{ $mailReady ? __('settings.mail_status_on') : __('settings.mail_status_off') }}
        </p>

        <div class="mt-5 grid gap-5">
            <div>
                <label for="mail_transport" class="block text-sm font-medium text-slate-700">{{ __('settings.mail_transport') }}</label>
                <select id="mail_transport" name="mail_transport"
                        class="mt-1 w-72 rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <option value="log" @selected(old('mail_transport', $mail['transport']) === 'log')>{{ __('settings.mail_transport_log') }}</option>
                    <option value="smtp" @selected(old('mail_transport', $mail['transport']) === 'smtp')>{{ __('settings.mail_transport_smtp') }}</option>
                </select>
                <p class="mt-1 text-xs text-slate-500">{{ __('settings.mail_transport_help') }}</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="mail_host" class="block text-sm font-medium text-slate-700">{{ __('settings.mail_host') }}</label>
                    <input type="text" id="mail_host" name="mail_host" maxlength="255"
                           value="{{ old('mail_host', $mail['host']) }}"
                           class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @error('mail_host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mail_port" class="block text-sm font-medium text-slate-700">{{ __('settings.mail_port') }}</label>
                    <input type="number" id="mail_port" name="mail_port" min="1" max="65535"
                           value="{{ old('mail_port', $mail['port']) }}"
                           class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @error('mail_port') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="mail_encryption" class="block text-sm font-medium text-slate-700">{{ __('settings.mail_encryption') }}</label>
                    <select id="mail_encryption" name="mail_encryption"
                            class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        @foreach (['tls', 'ssl', 'none'] as $enc)
                            <option value="{{ $enc }}" @selected(old('mail_encryption', $mail['encryption']) === $enc)>{{ __("settings.mail_enc_{$enc}") }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="mail_username" class="block text-sm font-medium text-slate-700">{{ __('settings.mail_username') }}</label>
                    <input type="text" id="mail_username" name="mail_username" maxlength="255"
                           autocomplete="off" value="{{ old('mail_username', $mail['username']) }}"
                           class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="mail_password" class="block text-sm font-medium text-slate-700">{{ __('settings.mail_password') }}</label>
                    <input type="password" id="mail_password" name="mail_password" maxlength="500"
                           autocomplete="new-password"
                           placeholder="{{ $mail['has_password'] ? __('settings.mail_password_stored') : __('settings.mail_password_placeholder') }}"
                           class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @if ($mail['has_password'])
                        <label class="mt-2 flex items-center gap-2 text-xs text-slate-600">
                            <input type="checkbox" name="mail_password_clear" value="1"
                                   class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                            {{ __('settings.mail_password_clear') }}
                        </label>
                    @endif
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="mail_from_address" class="block text-sm font-medium text-slate-700">{{ __('settings.mail_from_address') }}</label>
                    <input type="email" id="mail_from_address" name="mail_from_address" maxlength="255"
                           value="{{ old('mail_from_address', $mail['from_address']) }}"
                           class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @error('mail_from_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mail_from_name" class="block text-sm font-medium text-slate-700">{{ __('settings.mail_from_name') }}</label>
                    <input type="text" id="mail_from_name" name="mail_from_name" maxlength="150"
                           value="{{ old('mail_from_name', $mail['from_name']) }}"
                           class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="mail_to_address" class="block text-sm font-medium text-slate-700">{{ __('settings.mail_to') }}</label>
                    <input type="email" id="mail_to_address" name="mail_to_address" maxlength="255"
                           value="{{ old('mail_to_address', $mail['to_address']) }}"
                           class="mt-1 w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <p class="mt-1 text-xs text-slate-500">{{ __('settings.mail_to_help') }}</p>
                    @error('mail_to_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                {{ __('common.save') }}
            </button>
            <span class="text-xs text-slate-500">{{ __('settings.live_preview_note') }}</span>
        </div>
    </form>

    <div class="mt-6 max-w-3xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-semibold text-slate-800">{{ __('settings.mail_test') }}</h3>
        <p class="mt-1 text-sm text-slate-500">
            {{ __('settings.mail_test_help', ['address' => $mailTestAddress]) }}
        </p>
        <form method="POST" action="{{ route('admin.configuracoes.email.teste') }}" class="mt-4">
            @csrf
            <button type="submit"
                    class="rounded-lg border border-primary-600 px-4 py-2 text-sm font-semibold text-primary-600 hover:bg-primary-50">
                {{ __('settings.mail_test_send') }}
            </button>
        </form>
    </div>

    <script>
        document.querySelectorAll('input[name="mode"]').forEach(function (input) {
            input.addEventListener('change', function () {
                document.documentElement.classList.toggle('dark', input.value === 'escuro');
                document.documentElement.dataset.adminMode = input.value;
            });
        });

        document.querySelectorAll('input[name="color"]').forEach(function (input) {
            input.addEventListener('change', function () {
                document.documentElement.dataset.adminColor = input.value;
            });
        });

        function pickSettingsImage(name, previewId) {
            var picker = document.getElementById('media-picker');
            picker.dataset.target = '[name="' + name + '"]';
            picker.dataset.preview = '#' + previewId;
            openMediaPicker();
        }

        function clearSettingsImage(name, previewId) {
            document.querySelector('[name="' + name + '"]').value = '';
            var preview = document.getElementById(previewId);
            preview.src = '';
            preview.classList.add('hidden');
        }
    </script>

    @include('media::picker', [
        'mode' => 'single',
        'targetField' => '',
        'title' => __('settings.choose_image'),
    ])
@endsection
