<?php

namespace App\Modules\Media\Services;

use App\Modules\Media\Models\Medium;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaStorage
{
    /**
     * Supported image types and their canonical extensions.
     *
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * Supported video types — stored verbatim (no GD/ffmpeg; the serve route
     * streams them with Content-Type by extension and Range support).
     *
     * @var array<string, string>
     */
    private const VIDEO_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/ogg' => 'ogv',
    ];

    /**
     * Some libmagic builds report mp4/ogg containers as application/* when the
     * payload has no audio track hints; browsers declare them as video/*.
     *
     * @var array<string, string>
     */
    private const VIDEO_ALIASES = [
        'application/mp4' => 'video/mp4',
        'application/ogg' => 'video/ogg',
    ];

    /**
     * Classify an upload as image or video.
     *
     * Images require the *sniffed* type to match (a spoofed client mime must
     * never reach GD). Videos accept the sniffed or the client-declared type,
     * because container mimes vary between libmagic versions.
     *
     * @return array{kind: string, mime: string}|null
     */
    public static function classify(UploadedFile $file): ?array
    {
        $sniff = (string) $file->getMimeType();

        if (isset(self::EXTENSIONS[$sniff])) {
            return ['kind' => 'image', 'mime' => $sniff];
        }

        $candidates = array_unique([$sniff, (string) $file->getClientMimeType()]);

        foreach ($candidates as $mime) {
            if (isset(self::VIDEO_EXTENSIONS[$mime]) || isset(self::VIDEO_ALIASES[$mime])) {
                return ['kind' => 'video', 'mime' => self::VIDEO_ALIASES[$mime] ?? $mime];
            }
        }

        return null;
    }

    public static function detectKind(UploadedFile $file): ?string
    {
        return self::classify($file)['kind'] ?? null;
    }

    public static function isVideoMime(string $mime): bool
    {
        return isset(self::VIDEO_EXTENSIONS[$mime]) || isset(self::VIDEO_ALIASES[$mime]);
    }

    /**
     * Store an uploaded image or video. Images are resized (when above the
     * max dimension) and re-encoded; videos (and animated GIFs) are kept as-is.
     *
     * @throws RuntimeException when the file cannot be processed
     */
    public function store(UploadedFile $file, ?int $userId = null): Medium
    {
        $kind = self::detectKind($file);

        if ($kind === 'video') {
            $mime = (string) self::classify($file)['mime'];

            return $this->storeVerbatim($file, $mime, self::VIDEO_EXTENSIONS[$mime], $userId);
        }

        $mime = (string) $file->getMimeType();

        if (! isset(self::EXTENSIONS[$mime])) {
            throw new RuntimeException('Tipo de arquivo não suportado: '.$mime);
        }

        $extension = self::EXTENSIONS[$mime];

        try {
            $processed = $this->process($file, $mime);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            throw new RuntimeException('Não foi possível processar o arquivo.');
        }

        $now = now();
        $relativePath = $now->format('Y').'/'.$now->format('m').'/'.Str::random(40).'.'.$extension;

        Storage::disk('local')->put('media/'.$relativePath, $processed['binary']);

        return Medium::create([
            'filename' => $file->getClientOriginalName(),
            'path' => $relativePath,
            'mime_type' => $mime,
            'size' => strlen($processed['binary']),
            'width' => $processed['width'],
            'height' => $processed['height'],
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * Videos (and other pass-through types): bytes copied untouched; the media
     * route streams them with Range support so players can seek.
     *
     * @throws RuntimeException
     */
    private function storeVerbatim(UploadedFile $file, string $mime, string $extension, ?int $userId): Medium
    {
        $binary = (string) file_get_contents((string) $file->getRealPath());

        if ($binary === '') {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        $now = now();
        $relativePath = $now->format('Y').'/'.$now->format('m').'/'.Str::random(40).'.'.$extension;

        Storage::disk('local')->put('media/'.$relativePath, $binary);

        return Medium::create([
            'filename' => $file->getClientOriginalName(),
            'path' => $relativePath,
            'mime_type' => $mime,
            'size' => strlen($binary),
            'width' => null,
            'height' => null,
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * Resize (when above cms.media.max_dimension) and re-encode the image.
     *
     * @return array{binary: string, width: int|null, height: int|null}
     *
     * @throws RuntimeException
     */
    private function process(UploadedFile $file, string $mime): array
    {
        $realPath = (string) $file->getRealPath();
        $dimensions = @getimagesize($realPath);

        if ($dimensions === false) {
            throw new RuntimeException('O arquivo não é uma imagem válida.');
        }

        [$width, $height] = [$dimensions[0], $dimensions[1]];

        // GIFs are copied verbatim to preserve animations (GD would flatten them).
        if ($mime === 'image/gif') {
            return [
                'binary' => (string) file_get_contents($realPath),
                'width' => $width,
                'height' => $height,
            ];
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($realPath),
            'image/png' => @imagecreatefrompng($realPath),
            'image/webp' => @imagecreatefromwebp($realPath),
        };

        if ($source === false || $source === null) {
            throw new RuntimeException('Não foi possível ler a imagem.');
        }

        // PNG/WebP keep their alpha only if GD is told to save it; without the
        // flag the re-encode flattens transparency onto the black default of a
        // truecolor canvas — the reported "transparent logos got black ground".
        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($source, false);
            imagesavealpha($source, true);
        }

        $max = (int) config('cms.media.max_dimension', 2000);

        if ($width > $max || $height > $max) {
            $ratio = min($max / $width, $max / $height);
            $newWidth = max(1, (int) round($width * $ratio));
            $newHeight = max(1, (int) round($height * $ratio));

            $resized = imagecreatetruecolor($newWidth, $newHeight);

            if (in_array($mime, ['image/png', 'image/webp'], true)) {
                // Fill transparent with blending OFF, then blend the resample
                // itself: imagecopyresampled averages RGB *without* weighting
                // by alpha, so a fully-transparent neighbor (whose RGB is
                // black) darkens edge pixels — the "dark halo / black ground"
                // symptom on resized logos. Blending composites correctly.
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
                imagealphablending($resized, true);
            }

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);

            $source = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        $quality = (int) config('cms.media.quality', 85);

        // savealpha survives the resize branch too (the flag lives on the
        // resized canvas); re-assert here for the no-resize path's source.
        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagesavealpha($source, true);
            imagealphablending($source, false);
        }

        ob_start();

        match ($mime) {
            'image/jpeg' => imagejpeg($source, null, $quality),
            'image/png' => imagepng($source),
            'image/webp' => imagewebp($source, null, $quality),
        };

        $binary = (string) ob_get_clean();
        imagedestroy($source);

        if ($binary === '') {
            throw new RuntimeException('Não foi possível gravar a imagem.');
        }

        return ['binary' => $binary, 'width' => $width, 'height' => $height];
    }
}
