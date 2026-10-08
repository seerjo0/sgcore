<?php

namespace App\Modules\Pages\Http\Controllers\Admin;

use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\MenuItem;
use App\Modules\Pages\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class MenuController extends Controller
{
    /**
     * List all menus.
     */
    public function index(): View
    {
        $menus = Menu::withCount('items')->orderBy('title')->paginate(15);

        return view('pages::menus.index', compact('menus'));
    }

    /**
     * Show the menu creation form.
     */
    public function create(): View
    {
        return view('pages::menus.create', [
            'locations' => $this->availableLocations(),
        ]);
    }

    /**
     * Persist a new menu.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'location' => ['required', Rule::in(array_keys(Menu::LOCATIONS)), Rule::unique('menus', 'location')],
        ]);

        $menu = Menu::create($data);

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('success', 'Menu criado. Agora adicione os itens.');
    }

    /**
     * Show the menu editor with its items.
     */
    public function edit(Menu $menu): View
    {
        $items = $menu->items()->with('page')->get();

        return view('pages::menus.edit', [
            'menu' => $menu,
            'roots' => $items->whereNull('parent_id')->values(),
            'children' => $items->whereNotNull('parent_id')->groupBy('parent_id'),
            'pages' => Page::orderBy('title')->get(['id', 'title']),
            'locations' => Menu::LOCATIONS,
        ]);
    }

    /**
     * Update the menu title/location.
     */
    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'location' => ['required', Rule::in(array_keys(Menu::LOCATIONS)), Rule::unique('menus', 'location')->ignore($menu->id)],
        ]);

        $menu->update($data);

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('success', 'Menu atualizado com sucesso.');
    }

    /**
     * Delete the menu (items cascade).
     */
    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();

        return redirect()
            ->route('admin.menus.index')
            ->with('success', 'Menu excluído com sucesso.');
    }

    /**
     * Add an item to the menu (appended to its sibling group).
     */
    public function storeItem(Request $request, Menu $menu): RedirectResponse
    {
        $validator = $this->itemValidator($request, $menu);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors())->withInput();
        }

        $data = $this->itemData($request);
        $parentId = $data['parent_id'];
        $data['sort_order'] = $this->nextSort($menu, $parentId);

        MenuItem::create($data + ['menu_id' => $menu->id]);

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('success', 'Item adicionado ao menu.');
    }

    /**
     * Show the item editor.
     */
    public function editItem(Menu $menu, MenuItem $item): View
    {
        return view('pages::menus.items.edit', [
            'menu' => $menu,
            'item' => $item,
            'pages' => Page::orderBy('title')->get(['id', 'title']),
            'parents' => $menu->rootItems()->whereKeyNot($item->id)->get(),
        ]);
    }

    /**
     * Edit an existing item.
     */
    public function updateItem(Request $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        $validator = $this->itemValidator($request, $menu, $item);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors())->withInput();
        }

        $data = $this->itemData($request);

        if ($data['parent_id'] !== $item->parent_id) {
            $data['sort_order'] = $this->nextSort($menu, $data['parent_id']);
        }

        $item->fill($data)->save();

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('success', 'Item atualizado.');
    }

    /**
     * Move an item one position up or down among its siblings.
     */
    public function moveItem(Request $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        $direction = $request->input('direcao');

        if (! in_array($direction, ['up', 'down'], true)) {
            return back();
        }

        $siblings = $this->siblings($menu, $item->parent_id)->get()->values();

        $index = $siblings->search(fn (MenuItem $candidate): bool => $candidate->is($item));

        if ($index === false) {
            return back();
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= $siblings->count()) {
            return back();
        }

        // Renormalize first so swaps work even with duplicate orders.
        foreach ($siblings->values() as $position => $candidate) {
            if ((int) $candidate->sort_order !== $position) {
                $candidate->sort_order = $position;
                $candidate->save();
            }
        }

        $swap = $siblings[$target];
        $item->sort_order = $target;
        $swap->sort_order = $index;
        $item->save();
        $swap->save();

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('success', 'Ordem atualizada.');
    }

    /**
     * Remove an item (its children cascade).
     */
    public function destroyItem(Menu $menu, MenuItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('success', 'Item removido.');
    }

    /**
     * Validation for create/edit item, including type-specific rules and the
     * "parent must be a top-level item of this same menu" constraint.
     */
    private function itemValidator(Request $request, Menu $menu, ?MenuItem $item = null): Validator
    {
        $validator = ValidatorFactory::make($request->all(), [
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['page', 'custom', 'anchor'])],
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'url' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
        ], [
            'label.required' => 'Informe o rótulo do item.',
            'type.in' => 'Tipo de item inválido.',
            'page_id.exists' => 'A página selecionada não existe.',
            'parent_id.exists' => 'O item pai selecionado não existe.',
        ]);

        $validator->after(function (Validator $validator) use ($request, $menu, $item): void {
            $type = (string) $request->input('type');

            if ($type === 'page' && ! $request->filled('page_id')) {
                $validator->errors()->add('page_id', 'Selecione a página do link.');
            }

            if ($type === 'custom' && ! $request->filled('url')) {
                $validator->errors()->add('url', 'Informe a URL do link.');
            }

            if ($type === 'anchor' && ! str_starts_with((string) $request->input('url'), '#')) {
                $validator->errors()->add('url', 'Links âncora devem começar com "#".');
            }

            if ($request->filled('parent_id')) {
                $parent = MenuItem::find((int) $request->input('parent_id'));
                $reason = $this->parentError($parent, $menu, $item);

                if ($reason !== null) {
                    $validator->errors()->add('parent_id', $reason);
                }
            }
        });

        return $validator;
    }

    private function parentError(?MenuItem $parent, Menu $menu, ?MenuItem $item): ?string
    {
        if ($parent === null) {
            return 'O item pai selecionado não existe.';
        }

        if ($parent->menu_id !== $menu->id) {
            return 'O item pai pertence a outro menu.';
        }

        if ($parent->parent_id !== null) {
            return 'Só é permitido um nível de aninhamento: escolha um item de nível superior.';
        }

        if ($item !== null && ($parent->is($item) || $this->isDescendant($parent, $item))) {
            return 'Um item não pode ser filho dele mesmo ou de um descendente.';
        }

        return null;
    }

    private function isDescendant(MenuItem $candidate, MenuItem $item): bool
    {
        $parent = $candidate->parent;

        while ($parent !== null) {
            if ($parent->is($item)) {
                return true;
            }

            $parent = $parent->parent;
        }

        return false;
    }

    /**
     * @return array{label: string, type: string, page_id: int|null, url: string|null, parent_id: int|null}
     */
    private function itemData(Request $request): array
    {
        $type = (string) $request->input('type');
        $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;

        return [
            'label' => (string) $request->input('label'),
            'type' => $type,
            'page_id' => $type === 'page' && $request->filled('page_id') ? (int) $request->input('page_id') : null,
            'url' => $type !== 'page' && $request->filled('url') ? (string) $request->input('url') : null,
            'parent_id' => $parentId,
        ];
    }

    private function nextSort(Menu $menu, ?int $parentId): int
    {
        $max = $this->siblings($menu, $parentId)->max('sort_order');

        return ($max === null ? -1 : (int) $max) + 1;
    }

    private function siblings(Menu $menu, ?int $parentId): Builder
    {
        return MenuItem::query()
            ->where('menu_id', $menu->id)
            ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'), fn ($query) => $query->where('parent_id', $parentId))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Locations still free to pick when creating a menu.
     *
     * @return array<string, string>
     */
    private function availableLocations(): array
    {
        $used = Menu::pluck('location')->all();

        return array_diff_key(Menu::LOCATIONS, array_flip($used));
    }
}
