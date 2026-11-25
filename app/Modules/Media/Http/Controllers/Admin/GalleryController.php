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
     * List all galleries in their display order (the arrows next to each
     * row define what themes/modules show first).
     */
    public function index(): View
    {
        $galleries = Gallery::withCount('items')->ordered()->paginate(15);

        return view('media::galleries.index', compact('galleries'));
    }

    /**
     * Move a gallery up/down in the public display order (themes, pickers
     * and modules list galleries through the arrows chosen here).
     */
    public function move(Request $request, Gallery $gallery): RedirectResponse
    {
        $direction = $request->input('direcao');

        if (! in_array($direction, ['up', 'down'], true)) {
            return back();
        }

        $galleries = Gallery::query()->ordered()->get()->values();

        $index = $galleries->search(fn (Gallery $candidate): bool => $candidate->is($gallery));

        if ($index === false) {
            return back();
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= $galleries->count()) {
            return back();
        }

        // Renormaliza 1..n antes de trocar — swaps funcionam mesmo com
        // sort_order duplicado (linhas antigas com default 0).
        foreach ($galleries as $position => $candidate) {
            if ((int) $candidate->sort_order !== $position + 1) {
                $candidate->sort_order = $position + 1;
                $candidate->save();
            }
        }

        $swap = $galleries[$target];
        $gallery->sort_order = $target + 1;
        $swap->sort_order = $index + 1;
        $gallery->save();
        $swap->save();

        return redirect()
            ->route('admin.galerias.index')
            ->with('success', __('galleries.order_updated'));
    }

    /**
     * Show the gallery creation form.
     */
    public function create(): View
    {
        return view('media::galleries.create');
    }

    /**
     * Persist a new gallery. It enters FIRST (the historic public order —
     * newest on top), so the upgrade changes nothing; the arrows reorder it.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        Gallery::query()->increment('sort_order');

        $gallery = Gallery::create($data);
        $gallery->sort_order = 1;
        $gallery->save();

        return redirect()
            ->route('admin.galerias.edit', $gallery)
            ->with('success', __('galleries.created'));
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
            ->with('success', __('galleries.updated'));
    }

    /**
     * Delete the gallery (items cascade; media files are kept).
     */
    public function destroy(Gallery $gallery): RedirectResponse
    {
        $gallery->delete();

        return redirect()
            ->route('admin.galerias.index')
            ->with('success', __('galleries.deleted'));
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
            ->with('success', __('galleries.image_added'));
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
            ->with('success', __('galleries.caption_saved'));
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
            ->with('success', __('galleries.order_updated'));
    }

    /**
     * Remove an item from the gallery (the media file is kept).
     */
    public function destroyItem(Gallery $gallery, GalleryItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()
            ->route('admin.galerias.edit', $gallery)
            ->with('success', __('galleries.image_removed'));
    }
}
