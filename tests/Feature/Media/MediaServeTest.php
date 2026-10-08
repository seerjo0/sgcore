<?php

namespace Tests\Feature\Media;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaServeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
        Storage::fake('local');
    }

    public function test_serves_an_existing_media_file(): void
    {
        $image = (string) file_get_contents($this->tempJpeg());

        Storage::disk('local')->put('media/2026/10/exemplo.jpg', $image);

        $response = $this->get('/media/2026/10/exemplo.jpg');

        $response->assertOk()->assertHeader('content-type', 'image/jpeg');

        // BinaryFileResponse streams from disk, so assert the file it points to.
        $this->assertSame($image, file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    public function test_returns_404_for_missing_file(): void
    {
        $this->get('/media/2026/10/nao-existe.jpg')->assertNotFound();
    }

    public function test_blocks_path_traversal_outside_the_media_directory(): void
    {
        // A file sitting next to the media directory must not be reachable.
        Storage::disk('local')->put('secreto.jpg', 'conteudo-secreto');
        Storage::disk('local')->put('media/ok.txt', 'publico');

        $this->assertFileExists(Storage::disk('local')->path('secreto.jpg'));

        $this->get('/media/nao-existe/../../secreto.jpg')->assertNotFound();
        $this->get('/media/../secreto.jpg')->assertNotFound();
        $this->get('/media/%2e%2e/secreto.jpg')->assertNotFound();

        // Sanity check: the guard does not block legitimate paths.
        $this->get('/media/ok.txt')->assertOk();
    }

    /**
     * Write a small real JPEG to a temp file and return its path.
     */
    private function tempJpeg(): string
    {
        $image = imagecreatetruecolor(8, 8);

        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $path = tempnam(sys_get_temp_dir(), 'sgcore').'.jpg';
        file_put_contents($path, $bytes);

        return $path;
    }
}
