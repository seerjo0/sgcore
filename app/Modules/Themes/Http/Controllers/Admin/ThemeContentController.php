<?php

namespace App\Modules\Themes\Http\Controllers\Admin;

use App\Modules\Media\Models\Gallery;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Page;
use App\Modules\Pages\Services\ContentSanitizer;
use App\Modules\Themes\Services\ContentStore;
use App\Modules\Themes\Services\Theme;
use App\Modules\Themes\Services\ThemeDiscovery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Dynamic slot-editing form generated from the active theme's theme.json
 * (global section + per-page section).
 *
 * Slot ids contain dots ("site.name"), which Laravel rule keys cannot address
 * (they split on dots into nested paths), so validation is done per slot here.
 */
class ThemeContentController extends Controller
{
    /**
     * Slot types the admin can edit. Menu locations are managed in Menus.
     *
     * @var list<string>
     */
    private const EDITABLE_TYPES = ['text', 'richtext', 'image', 'gallery', 'logo'];

    private const TEXT_MAX = 500;

    private const RICHTEXT_MAX = 20000;

    public function __construct(
        private ThemeDiscovery $discovery,
        private ContentStore $contents,
        private ContentSanitizer $sanitizer,
    ) {}

    /**
     * GET /admin/aparencia/conteudo — global slots + page selector.
     */
    public function global(): View|RedirectResponse
    {
        $theme = $this->activeTheme();

        if ($theme === null) {
            return $this->noThemeRedirect();
        }

        return view('themes::admin.appearance.content', $this->payload($theme, null));
    }

    /**
     * GET /admin/aparencia/conteudo/pagina/{page} — page-scoped slots.
     */
    public function page(Page $page): View|RedirectResponse
    {
        $theme = $this->activeTheme();

        if ($theme === null) {
            return $this->noThemeRedirect();
        }

        return view('themes::admin.appearance.content', $this->payload($theme, $page));
    }

    /**
     * POST /admin/aparencia/conteudo — save global slots.
     */
    public function saveGlobal(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    /**
     * POST /admin/aparencia/conteudo/pagina/{page} — save page slots.
     */
    public function savePage(Request $request, Page $page): RedirectResponse
    {
        return $this->save($request, $page);
    }

    private function save(Request $request, ?Page $page): RedirectResponse
    {
        $theme = $this->activeTheme();

        if ($theme === null) {
            return $this->noThemeRedirect();
        }

        $specs = $this->editableSlots($theme, $page === null ? 'global' : 'page');
        $input = $request->input('slots', []);

        if (! is_array($input)) {
            $input = [];
        }

        $errors = [];
        $values = [];

        foreach ($specs as $id => $spec) {
            if (! array_key_exists($id, $input)) {
                continue;
            }

            [$clean, $error] = $this->cleanSlot($spec, $input[$id]);

            if ($error !== null) {
                $errors['slots.'.$id] = $error;

                continue;
            }

            $values[$id] = $clean;
        }

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        $this->contents->put(
            $page === null ? 'global' : 'theme',
            $theme->slug,
            $page?->id ?? 0,
            $values,
        );

        $message = $page === null
            ? 'Conteúdo global salvo.'
            : 'Conteúdo da página “'.$page->title.'” salvo.';

        $redirect = $page === null
            ? redirect()->route('admin.aparencia.conteudo')->with('success', $message)
            : redirect()->route('admin.aparencia.conteudo.pagina', $page)->with('success', $message);

        return $redirect;
    }

    /**
     * Validate and normalize a single submitted slot value.
     *
     * @param  array<string, mixed>  $spec
     * @return array{0: mixed, 1: ?string} [clean value, pt-BR error]
     */
    private function cleanSlot(array $spec, mixed $value): array
    {
        $type = is_string($spec['type'] ?? null) ? $spec['type'] : 'text';

        if ($value === null || $value === '') {
            return [null, null];
        }

        if (is_array($value)) {
            return [null, 'Valor inválido para o slot.'];
        }

        $value = is_scalar($value) ? (string) $value : '';

        return match ($type) {
            'image', 'logo' => $this->cleanMediaId($value, 'Selecione uma imagem válida da biblioteca.'),
            'gallery' => $this->cleanMediaId($value, 'Selecione uma galeria válida.', Gallery::class),
            'richtext' => $this->cleanRichtext($value),
            default => $this->cleanText($value),
        };
    }

    /**
     * @return array{0: mixed, 1: ?string}
     */
    private function cleanText(string $value): array
    {
        $value = trim($value);

        if (mb_strlen($value) > self::TEXT_MAX) {
            return [null, 'O texto não pode ter mais de '.self::TEXT_MAX.' caracteres.'];
        }

        return [$value, null];
    }

    /**
     * @return array{0: mixed, 1: ?string}
     */
    private function cleanRichtext(string $value): array
    {
        if (mb_strlen($value) > self::RICHTEXT_MAX) {
            return [null, 'O conteúdo não pode ter mais de '.self::RICHTEXT_MAX.' caracteres.'];
        }

        return [(string) $this->sanitizer->sanitize($value), null];
    }

    /**
     * Image/gallery slots store the library record id (what the picker writes).
     *
     * @param  class-string<Model>|null  $model
     * @return array{0: mixed, 1: ?string}
     */
    private function cleanMediaId(string $value, string $message, ?string $model = Medium::class): array
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
            return [null, $message];
        }

        $id = (int) $value;

        if ($id <= 0 || $model === null || $model::query()->find($id) === null) {
            return [null, $message];
        }

        return [$id, null];
    }

    /**
     * @param  array<string, array<string, mixed>>  $specs
     * @return array<string, array<string, mixed>>
     */
    private function payload(Theme $theme, ?Page $page): array
    {
        $specs = $this->editableSlots($theme, $page === null ? 'global' : 'page');

        $values = $page === null
            ? $this->contents->values('global', '', 0)
            : $this->contents->values('theme', $theme->slug, $page->id);

        return [
            'theme' => $theme,
            'page' => $page,
            'slots' => $specs,
            'values' => $values,
            'previews' => $this->previews($specs, $values),
            'pages' => Page::query()->orderByDesc('is_home')->orderBy('title')->get(),
            'galleries' => Gallery::query()->orderBy('title')->get(),
        ];
    }

    /**
     * Editable slot specs of the given scope, keyed by slot id.
     *
     * @return array<string, array<string, mixed>>
     */
    private function editableSlots(Theme $theme, string $scope): array
    {
        $slots = [];

        foreach ($theme->slots() as $id => $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $slotScope = ($spec['scope'] ?? 'page') === 'global' ? 'global' : 'page';

            if ($slotScope !== $scope) {
                continue;
            }

            $type = is_string($spec['type'] ?? null) ? $spec['type'] : 'text';

            if (! in_array($type, self::EDITABLE_TYPES, true)) {
                continue;
            }

            $slots[$id] = $spec;
        }

        return $slots;
    }

    /**
     * Preview URL per image slot (media id, legacy path or full URL).
     *
     * @param  array<string, array<string, mixed>>  $specs
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function previews(array $specs, array $values): array
    {
        $previews = [];

        foreach ($specs as $id => $spec) {
            $type = is_string($spec['type'] ?? null) ? $spec['type'] : 'text';

            if (! in_array($type, ['image', 'logo'], true)) {
                continue;
            }

            $url = $this->previewUrl($values[$id] ?? null);

            if ($url !== '') {
                $previews[$id] = $url;
            }
        }

        return $previews;
    }

    private function previewUrl(mixed $value): string
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            return Medium::query()->find((int) $value)?->url() ?? '';
        }

        if (! is_string($value) || $value === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $value) === 1 || str_starts_with($value, '/')) {
            return $value;
        }

        return '/media/'.ltrim($value, '/');
    }

    private function activeTheme(): ?Theme
    {
        $theme = $this->discovery->active();

        return $theme !== null && $theme->valid ? $theme : null;
    }

    private function noThemeRedirect(): RedirectResponse
    {
        return redirect()
            ->route('admin.aparencia.index')
            ->withErrors(['theme' => 'Nenhum tema ativo. Ative um tema em Aparência.']);
    }
}
