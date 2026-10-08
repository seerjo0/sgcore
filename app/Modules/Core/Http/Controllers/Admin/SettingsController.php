<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Modules\Core\Services\AdminPreferences;
use App\Modules\Core\Services\Settings;
use App\Modules\Media\Models\Medium;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private AdminPreferences $preferences,
        private Settings $settings,
    ) {}

    /**
     * GET /admin/configuracoes — admin preferences + site/SEO defaults.
     */
    public function index(): View
    {
        return view('core::admin.settings.index', [
            'modes' => $this->preferences->modes(),
            'colors' => $this->preferences->colors(),
            'currentMode' => $this->preferences->mode(),
            'currentColor' => $this->preferences->color(),
            'adminPath' => $this->preferences->adminPath(),
            'siteName' => (string) $this->settings->get('site_name', config('app.name')),
            'siteLogoId' => (string) ($this->settings->get('site_logo') ?? ''),
            'siteLogoUrl' => $this->mediaUrl((string) ($this->settings->get('site_logo') ?? '')),
            'faviconId' => (string) ($this->settings->get('site_favicon') ?? ''),
            'faviconUrl' => $this->mediaUrl((string) ($this->settings->get('site_favicon') ?? '')),
            'seoTitle' => (string) $this->settings->get('seo_title', ''),
            'seoDescription' => (string) $this->settings->get('seo_description', ''),
            'seoOgImageId' => (string) ($this->settings->get('seo_og_image') ?? ''),
            'seoOgImageUrl' => $this->mediaUrl((string) ($this->settings->get('seo_og_image') ?? '')),
        ]);
    }

    /**
     * POST /admin/configuracoes — persist admin preferences and site/SEO defaults.
     *
     * Site/SEO fields use `sometimes` so older clients posting only mode/color
     * keep working; every submitted key is saved (empty media/text clears to null).
     */
    public function update(Request $request): RedirectResponse
    {
        if ($request->has('admin_path')) {
            $request->merge(['admin_path' => strtolower(trim((string) $request->input('admin_path')))]);
        }

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', array_keys($this->preferences->modes()))],
            'color' => ['required', 'string', 'in:'.implode(',', array_keys($this->preferences->colors()))],
            'site_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'site_logo' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'site_favicon' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'seo_og_image' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'admin_path' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                'not_in:'.implode(',', AdminPreferences::RESERVED_PATHS),
            ],
        ], [
            'admin_path.regex' => 'O caminho deve usar apenas letras minúsculas, números e hífens (ex.: backend, painel-01).',
            'admin_path.not_in' => 'Este caminho conflita com um endereço interno do sistema.',
            'admin_path.max' => 'O caminho pode ter no máximo 30 caracteres.',
        ]);

        $this->settings->set(AdminPreferences::KEY_MODE, $validated['mode'], 'admin');
        $this->settings->set(AdminPreferences::KEY_COLOR, $validated['color'], 'admin');

        $groups = [
            'site_name' => 'general',
            'site_logo' => 'site',
            'site_favicon' => 'site',
            'seo_title' => 'seo',
            'seo_description' => 'seo',
            'seo_og_image' => 'seo',
            'admin_path' => 'admin',
        ];

        $integers = ['site_logo', 'site_favicon', 'seo_og_image'];

        foreach ($groups as $key => $group) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $value = $validated[$key];

            if ($value === null || $value === '') {
                $value = null;
            } elseif (in_array($key, $integers, true)) {
                $value = (int) $value;
            }

            $this->settings->set($key, $value, $group);
        }

        // Changing admin_path moves the routes on the next boot, so the
        // redirect must already point at the new (or restored) address —
        // the old URL would 404 right after the save.
        if (array_key_exists('admin_path', $validated)) {
            $path = ($validated['admin_path'] === null || $validated['admin_path'] === '')
                ? $this->preferences->adminPath()
                : $validated['admin_path'];

            return redirect('/'.$path.'/configuracoes')
                ->with('success', 'Configurações atualizadas.');
        }

        return redirect()
            ->route('admin.configuracoes.index')
            ->with('success', 'Configurações atualizadas.');
    }

    /**
     * Resolve a stored media id to its public URL (empty string when unset/missing).
     */
    private function mediaUrl(string $id): string
    {
        return ctype_digit($id) && $id !== '0'
            ? Medium::query()->find((int) $id)?->url() ?? ''
            : '';
    }
}
