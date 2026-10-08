<?php

namespace App\Modules\Media\Http\Controllers\Admin;

use App\Modules\Media\Models\Medium;
use App\Modules\Media\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Throwable;

class MediaController extends Controller
{
    public function __construct(private MediaStorage $mediaStorage) {}

    /**
     * Media library grid with search and pagination.
     */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $media = Medium::query()
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($where) use ($q): void {
                    $where->where('filename', 'like', "%{$q}%")
                        ->orWhere('alt', 'like', "%{$q}%")
                        ->orWhere('caption', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('media::index', [
            'media' => $media,
            'q' => $q,
        ]);
    }

    /**
     * Store one or more uploaded images.
     */
    public function store(Request $request): RedirectResponse
    {
        $maxKb = (int) floor(((int) config('cms.media.max_upload_bytes')) / 1024);

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', 'max:'.$maxKb],
        ]);

        $userId = $request->user()?->id;
        $stored = 0;
        $failures = [];

        foreach ($request->file('files') as $file) {
            try {
                $this->mediaStorage->store($file, $userId);
                $stored++;
            } catch (Throwable $e) {
                $failures[] = $file->getClientOriginalName().' — '.$e->getMessage();
            }
        }

        if ($failures !== []) {
            return back()->withErrors(['files' => $failures]);
        }

        return back()->with('success', "{$stored} arquivo(s) enviado(s) com sucesso.");
    }

    /**
     * JSON endpoint used by the reusable media picker.
     */
    public function buscar(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 12), 1), 48);

        $media = Medium::query()
            ->when($q !== '', function ($query) use ($q): void {
                $query->where('filename', 'like', "%{$q}%")
                    ->orWhere('alt', 'like', "%{$q}%");
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $media->getCollection()->map(fn (Medium $medium): array => [
                'id' => $medium->id,
                'url' => $medium->url(),
                'filename' => $medium->filename,
                'alt' => $medium->alt,
                'caption' => $medium->caption,
            ]),
            'current_page' => $media->currentPage(),
            'last_page' => $media->lastPage(),
        ]);
    }

    /**
     * Show the edit form for a media record.
     */
    public function edit(Medium $medium): View
    {
        return view('media::edit', ['medium' => $medium]);
    }

    /**
     * Update alt text and caption.
     */
    public function update(Request $request, Medium $medium): RedirectResponse
    {
        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $medium->fill($data)->save();

        return redirect()
            ->route('admin.midia.index')
            ->with('success', 'Mídia atualizada com sucesso.');
    }

    /**
     * Delete the record and its file from disk.
     */
    public function destroy(Medium $medium): RedirectResponse
    {
        $medium->delete();

        return redirect()
            ->route('admin.midia.index')
            ->with('success', 'Mídia excluída com sucesso.');
    }
}
