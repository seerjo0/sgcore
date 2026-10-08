<?php

namespace App\Modules\Media\Http\Controllers\Admin;

use App\Modules\Media\Models\Gallery;
use App\Modules\Media\Models\GalleryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class GalleryController extends Controller
{
    /**
     * List all galleries.
     */
    public function index(): View
    {
        $galleries = Gallery::withCount('items')->orderBy('title')->paginate(15);

        return view('media::galleries.index', compact('galleries'));
    }

    /**
     * Show the gallery creation form.
     */
    public function create(): View
    {
        return view('media::galleries.create');
    }

    /**
     * Persist a new gallery.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $gallery = Gallery::create($data);

        return redirect()
            ->route('admin.galerias.edit', $gallery)
            ->with('success', 'Galeria criada. Agora adicione as imagens.');
    }

    /**
     * Show the gallery editor with its items.
     */
    public function edit(Gallery $gallery): View
    {
        $gallery->load('items.media');

        return view('media::galleries.edit', ['gallery' => $gallery]);
    }

    /**
     * Update the gallery title.
     */
    public function update(Request $request, Gallery $gallery): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $gallery->update($data);

        return redirect()
            ->route('admin.galerias.edit', $gallery)
            ->with('success', 'Galeria atualizada com sucesso.');
    }

    /**
     * Delete the gallery (items cascade; media files are kept).
     */
    public function destroy(Gallery $gallery): RedirectResponse
    {
        $gallery->delete();

        return redirect()
            ->route('admin.galerias.index')
            ->with('success', 'Galeria excluída com sucesso.');
    }

    /**
     * Add a media record to the gallery (idempotent).
     */
    public function storeItem(Request $request, Gallery $gallery): RedirectResponse
    {
        $data = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'exists:media,id'],
        ]);

        $next = ((int) $gallery->items()->max('sort_order')) + 1;

        foreach ($data['media_ids'] as $mediaId) {
            GalleryItem::query()->firstOrCreate(
                ['gallery_id' => $gallery->id, 'media_id' => (int) $mediaId],
                ['sort_order' => $next++],
            );
        }

        return redirect()
            ->route('admin.galerias.edit', $gallery)
            ->with('success', 'Imagem(ns) adicionada(s) à galeria.');
    }

    /**
     * Update the caption of a single item (must belong to the gallery).
     */
    public function updateItem(Request $request, Gallery $gallery, GalleryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $item->fill($data)->save();

        return redirect()
            ->route('admin.galerias.edit', $gallery)
            ->with('success', 'Legenda salva.');
    }

    /**
     * Move an item one position up or down.
     */
    public function moveItem(Request $request, Gallery $gallery, GalleryItem $item): RedirectResponse
    {
        $direction = $request->input('direcao');

        if (! in_array($direction, ['up', 'down'], true)) {
            return back();
        }

        $items = $gallery->items()->get()->values();

        $index = $items->search(fn (GalleryItem $candidate): bool => $candidate->is($item));

        if ($index === false) {
            return back();
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= $items->count()) {
            return back();
        }

        // Renormalize positions first so swaps work even with duplicate orders.
        foreach ($items->values() as $position => $candidate) {
            if ((int) $candidate->sort_order !== $position) {
                $candidate->sort_order = $position;
                $candidate->save();
            }
        }

        $swap = $items[$target];
        $item->sort_order = $target;
        $swap->sort_order = $index;
        $item->save();
        $swap->save();

        return redirect()
            ->route('admin.galerias.edit', $gallery)
            ->with('success', 'Ordem atualizada.');
    }

    /**
     * Remove an item from the gallery (the media file is kept).
     */
    public function destroyItem(Gallery $gallery, GalleryItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()
            ->route('admin.galerias.edit', $gallery)
            ->with('success', 'Imagem removida da galeria.');
    }
}
