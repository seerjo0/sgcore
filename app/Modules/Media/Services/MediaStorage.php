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
     * Store an uploaded image (validated mime), resizing oversized images and
     * re-encoding with the configured quality. Animated GIFs are kept as-is.
     *
     * @throws RuntimeException when the image cannot be processed
     */
    public function store(UploadedFile $file, ?int $userId = null): Medium
    {
        $mime = (string) $file->getMimeType();

        if (! isset(self::EXTENSIONS[$mime])) {
            throw new RuntimeException('Tipo de imagem não suportado: '.$mime);
        }

        $extension = self::EXTENSIONS[$mime];

        try {
            $processed = $this->process($file, $mime);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            throw new RuntimeException('Não foi possível processar a imagem.');
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

        $max = (int) config('cms.media.max_dimension', 2000);

        if ($width > $max || $height > $max) {
            $ratio = min($max / $width, $max / $height);
            $newWidth = max(1, (int) round($width * $ratio));
            $newHeight = max(1, (int) round($height * $ratio));

            $resized = imagecreatetruecolor($newWidth, $newHeight);

            if (in_array($mime, ['image/png', 'image/webp'], true)) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
            }

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);

            $source = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        $quality = (int) config('cms.media.quality', 85);

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
