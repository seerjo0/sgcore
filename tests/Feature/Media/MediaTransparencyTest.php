<?php

namespace Tests\Feature\Media;

use App\Modules\Media\Services\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MediaStorage re-encodes every upload via GD. GD silently drops the PNG/WebP
 * alpha channel unless imagesavealpha() is on — transparent logos came back
 * with a solid BLACK ground after upload (user report, v1.3.x).
 */
class MediaTransparencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
        Storage::fake('local');
    }

    private function transparentPng(int $size): string
    {
        $im = imagecreatetruecolor($size, $size);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledrectangle(
            $im,
            (int) ($size * 0.25),
            (int) ($size * 0.25),
            (int) ($size * 0.75),
            (int) ($size * 0.75),
            imagecolorallocatealpha($im, 255, 0, 0, 40)
        );

        $path = tempnam(sys_get_temp_dir(), 'alpha-').'.png';
        imagepng($im, $path);

        return $path;
    }

    /** Alpha as GD reads stored pixels: 0 = opaque, 127 = fully transparent. */
    private function alphaAt(string $path, int $x, int $y): int
    {
        $im = imagecreatefrompng($path);
        $pixel = imagecolorat($im, (int) (imagesx($im) * $x / 100), (int) (imagesy($im) * $y / 100));

        return ($pixel >> 24) & 0x7F;
    }

    private function storedPath(int $size): string
    {
        $src = $this->transparentPng($size);
        $file = new UploadedFile($src, 'logo.png', 'image/png', null, true);

        $media = app(MediaStorage::class)->store($file);
        @unlink($src);

        // store() writes to the 'media/' prefix inside the private disk; the
        // DB path is relative to that prefix (same convention as MediaUploadTest).
        Storage::disk('local')->assertExists('media/'.$media->path);

        return Storage::disk('local')->path('media/'.$media->path);
    }

    public function test_upload_keeps_transparency_without_resize(): void
    {
        config(['cms.media.max_dimension' => 2000]);

        $stored = $this->storedPath(100);

        // The 100x100 source: corner fully transparent, center semi (a=40).
        $this->assertSame(127, $this->alphaAt($stored, 2, 2), 'canto transparente virou opaco (fundo preto)');
        $center = $this->alphaAt($stored, 50, 50);
        $this->assertGreaterThan(0, $center, 'o centro semi virou totalmente opaco');
        $this->assertLessThan(127, $center);
    }

    public function test_upload_keeps_transparency_with_resize(): void
    {
        config(['cms.media.max_dimension' => 50]);

        $stored = $this->storedPath(100);

        $this->assertSame(127, $this->alphaAt($stored, 2, 2), 'resize: canto transparente virou fundo preto');
        $center = $this->alphaAt($stored, 50, 50);
        $this->assertGreaterThan(0, $center);
        $this->assertLessThan(127, $center);
    }
}
