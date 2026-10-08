<?php

namespace Tests\Feature\Media;

use App\Models\User;
use App\Modules\Media\Models\Medium;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
        Storage::fake('local');
    }

    /**
     * Build a real JPEG in memory so finfo detects the actual mime type.
     */
    private function jpegBytes(int $width = 50, int $height = 40): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }

        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function fakeImage(string $name = 'foto.jpg', ?string $bytes = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes ?? $this->jpegBytes());
    }

    public function test_guest_is_redirected_to_login_from_media_library(): void
    {
        $this->get(route('admin.midia.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_upload_image(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.midia.index'))
            ->post(route('admin.midia.store'), [
                'files' => [$this->fakeImage('foto-da-web.jpg')],
            ])
            ->assertRedirect(route('admin.midia.index'));

        $medium = Medium::query()->firstOrFail();

        $this->assertSame('foto-da-web.jpg', $medium->filename);
        $this->assertSame('image/jpeg', $medium->mime_type);
        $this->assertSame($admin->id, $medium->uploaded_by);
        $this->assertSame(50, $medium->width);
        $this->assertSame(40, $medium->height);
        $this->assertSame('/media/'.$medium->path, $medium->url());
        $this->assertStringStartsWith(now()->format('Y').'/'.now()->format('m').'/', $medium->path);

        Storage::disk('local')->assertExists('media/'.$medium->path);
    }

    public function test_editor_can_upload_image(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.midia.store'), [
                'files' => [$this->fakeImage()],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('media', 1);
    }

    public function test_upload_rejects_non_image_content(): void
    {
        $admin = User::factory()->admin()->create();
        $text = UploadedFile::fake()->createWithContent('notas.txt', 'isto nao e uma imagem');

        $this->actingAs($admin)
            ->from(route('admin.midia.index'))
            ->post(route('admin.midia.store'), ['files' => [$text]])
            ->assertRedirect(route('admin.midia.index'))
            ->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_rejects_files_above_the_size_limit(): void
    {
        config(['cms.media.max_upload_bytes' => 5 * 1024]);

        $admin = User::factory()->admin()->create();
        $big = $this->fakeImage('grande.jpg', $this->jpegBytes(400, 400));

        $this->assertGreaterThan(5 * 1024, $big->getSize());

        $this->actingAs($admin)
            ->from(route('admin.midia.index'))
            ->post(route('admin.midia.store'), ['files' => [$big]])
            ->assertRedirect(route('admin.midia.index'))
            ->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_oversized_images_are_resized_on_upload(): void
    {
        config(['cms.media.max_dimension' => 100]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.midia.store'), [
                'files' => [$this->fakeImage('grande.jpg', $this->jpegBytes(400, 200))],
            ])
            ->assertRedirect();

        $medium = Medium::query()->firstOrFail();

        $this->assertSame(100, $medium->width);
        $this->assertSame(50, $medium->height);
        Storage::disk('local')->assertExists('media/'.$medium->path);
    }

    public function test_admin_can_update_alt_and_caption(): void
    {
        $admin = User::factory()->admin()->create();
        $medium = Medium::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.midia.update', $medium), [
                'alt' => 'Praia ao pôr do sol',
                'caption' => 'Foto tirada em janeiro',
            ])
            ->assertRedirect(route('admin.midia.index'));

        $this->assertDatabaseHas('media', [
            'id' => $medium->id,
            'alt' => 'Praia ao pôr do sol',
            'caption' => 'Foto tirada em janeiro',
        ]);
    }

    public function test_delete_media_removes_record_and_file(): void
    {
        $admin = User::factory()->admin()->create();
        $medium = Medium::factory()->create();

        Storage::disk('local')->put('media/'.$medium->path, 'conteudo');

        $this->actingAs($admin)
            ->delete(route('admin.midia.destroy', $medium))
            ->assertRedirect(route('admin.midia.index'));

        $this->assertDatabaseMissing('media', ['id' => $medium->id]);
        Storage::disk('local')->assertMissing('media/'.$medium->path);
    }

    public function test_library_search_filters_by_filename(): void
    {
        $admin = User::factory()->admin()->create();
        Medium::factory()->create(['filename' => 'praia-sunset.jpg']);
        Medium::factory()->create(['filename' => 'outro-retrato.jpg']);

        $this->actingAs($admin)
            ->get(route('admin.midia.index', ['q' => 'praia']))
            ->assertOk()
            ->assertSee('praia-sunset.jpg')
            ->assertDontSee('outro-retrato.jpg');
    }

    public function test_picker_json_endpoint_returns_media(): void
    {
        $admin = User::factory()->admin()->create();
        $medium = Medium::factory()->create(['filename' => 'buscavel.jpg']);

        $this->actingAs($admin)
            ->get(route('admin.midia.buscar', ['q' => 'buscavel']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $medium->id)
            ->assertJsonPath('data.0.filename', 'buscavel.jpg')
            ->assertJsonPath('data.0.url', '/media/'.$medium->path);
    }
}
