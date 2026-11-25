<?php

namespace Tests\Feature\Media;

use App\Models\User;
use App\Modules\Media\Models\Gallery;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Page;
use App\Modules\Themes\Services\ContentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Videos in the media library: verbatim storage, type-aware limits, seekable
 * serving, mixed galleries rendering <video>, and the guard that keeps videos
 * out of image slots.
 */
class MediaVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** Minimal bytes finfo sniffs as the claimed video mime on this host. */
    private function videoBytes(string $kind = 'mp4'): string
    {
        return match ($kind) {
            'mp4' => "\x00\x00\x00\x18".'ftypmp42'."\x00\x00\x00\x00".'mp42isom'."\x00\x00\x00\x00",
            'webm' => "\x1A\x45\xDF\xA3\xA3\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF3\x81\x01\x42\xF2\x81\x01\x42\xF4\x81\x01\x42\xEB\x88\x64\x6E\x75\x6D\x6B\x79\x73\x74\x65\x72\x42\x82\x84\x77\x65\x62\x6D\x42\x87\x81\x02\x42\x85\x81\x02",
            default => throw new \InvalidArgumentException("kind {$kind}"),
        };
    }

    private function fakeVideo(string $name, string $kind = 'mp4'): UploadedFile
    {
        // createWithContent guesses the mime from the extension and picks
        // application/mp4 — declare the real video type (finfo on the temp
        // bytes reports video/mp4, matching production sniffing).
        $mime = $kind === 'webm' ? 'video/webm' : 'video/mp4';

        return UploadedFile::fake()->createWithContent($name, $this->videoBytes($kind), $mime);
    }

    private function fakeImage(string $name = 'foto.jpg'): UploadedFile
    {
        $im = imagecreatetruecolor(40, 30);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 20, 30));
        $path = tempnam(sys_get_temp_dir(), 'jpg-').'.jpg';
        imagejpeg($im, $path);

        return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path));
    }

    public function test_video_upload_is_stored_verbatim(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.midia.store'), ['files' => [$this->fakeVideo('aula.mp4')]])
            ->assertRedirect();

        $medium = Medium::query()->firstOrFail();

        $this->assertSame('video/mp4', $medium->mime_type);
        $this->assertTrue($medium->isVideo());
        $this->assertSame('video', $medium->kind());
        $this->assertStringEndsWith('.mp4', $medium->path);
        $this->assertNull($medium->width);

        Storage::disk('local')->assertExists('media/'.$medium->path);
        $this->assertSame($this->videoBytes('mp4'), Storage::disk('local')->get('media/'.$medium->path));
    }

    public function test_webm_upload_is_detected_and_stored(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.midia.store'), ['files' => [$this->fakeVideo('tela.webm', 'webm')]])
            ->assertRedirect();

        $medium = Medium::query()->firstOrFail();

        $this->assertSame('video/webm', $medium->mime_type);
        $this->assertStringEndsWith('.webm', $medium->path);
    }

    public function test_video_is_served_with_content_type_and_range_support(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.midia.store'), ['files' => [$this->fakeVideo('aula.mp4')]])
            ->assertRedirect();

        $medium = Medium::query()->firstOrFail();
        $bytes = strlen($this->videoBytes('mp4'));

        $full = $this->get('/media/'.$medium->path);
        $full->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Accept-Ranges', 'bytes');

        $partial = $this->withHeaders(['Range' => 'bytes=0-3'])->get('/media/'.$medium->path);
        $partial->assertStatus(206)
            ->assertHeader('Content-Range', "bytes 0-3/{$bytes}");
    }

    public function test_video_size_limit_is_independent_from_images(): void
    {
        config([
            'cms.media.max_upload_bytes' => 10 * 1024 * 1024,
            'cms.media.max_video_bytes' => 16,
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.midia.index'))
            ->post(route('admin.midia.store'), ['files' => [$this->fakeVideo('grande.mp4')]])
            ->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('media', 0);

        // an image within the image cap still passes under the SAME config
        $this->actingAs($this->admin())
            ->post(route('admin.midia.store'), ['files' => [$this->fakeImage()]])
            ->assertRedirect();

        $this->assertDatabaseCount('media', 1);
    }

    public function test_picker_json_reports_the_media_type(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.midia.store'), ['files' => [
                $this->fakeImage('foto.jpg'),
                $this->fakeVideo('aula.mp4'),
            ]])
            ->assertRedirect();

        $payload = $this->actingAs($this->admin())
            ->getJson(route('admin.midia.buscar'))
            ->assertOk()
            ->json('data');

        $types = array_column((array) $payload, 'type');
        $this->assertContains('image', $types);
        $this->assertContains('video', $types);
    }

    public function test_library_grid_badges_videos_and_the_editor_browser_can_see_them(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.midia.store'), ['files' => [$this->fakeVideo('aula.mp4')]])
            ->assertRedirect();

        $medium = Medium::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.midia.index'))
            ->assertOk()
            ->assertSee(__('media.badge_video'))
            ->assertSee('<video', false);

        $this->actingAs($this->admin())
            ->get(route('admin.midia.edit', $medium))
            ->assertOk()
            ->assertSee('<video', false);
    }

    public function test_gallery_renders_video_items_with_the_video_element(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.midia.store'), ['files' => [
            $this->fakeImage('foto.jpg'),
            $this->fakeVideo('aula.mp4'),
        ]])->assertRedirect();

        $image = Medium::query()->where('mime_type', 'image/jpeg')->firstOrFail();
        $video = Medium::query()->where('mime_type', 'video/mp4')->firstOrFail();

        $gallery = Gallery::query()->create(['title' => 'Mista']);
        $gallery->items()->create(['media_id' => $image->id, 'sort' => 0]);
        $gallery->items()->create(['media_id' => $video->id, 'sort' => 1, 'caption' => 'Assista']);

        $home = Page::query()->create([
            'title' => 'Início', 'slug' => 'inicio', 'status' => 'published', 'is_home' => true,
            'content' => ['body' => ''],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.pagina.salvar', $home), [
                'slots' => ['home.gallery' => (string) $gallery->id],
            ])
            ->assertRedirect();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<img src="/media/'.$image->path.'"', $html);
        $this->assertMatchesRegularExpression(
            '/<video[^>]+src="\/media\/'.preg_quote($video->path, '/').'"[^>]*controls/',
            $html
        );
        $this->assertStringNotContainsString('<img src="/media/'.$video->path.'"', $html);
        $this->assertStringContainsString('Assista', $html);
    }

    public function test_image_slot_rejects_a_video(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.midia.store'), ['files' => [$this->fakeVideo('aula.mp4')]])
            ->assertRedirect();

        $video = Medium::query()->firstOrFail();

        $home = Page::query()->create([
            'title' => 'Início', 'slug' => 'inicio', 'status' => 'published', 'is_home' => true,
            'content' => ['body' => ''],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.pagina.salvar', $home), [
                'slots' => ['home.hero_image' => (string) $video->id],
            ])
            ->assertSessionHasErrors('slots.home.hero_image');

        $this->assertNull(
            app(ContentStore::class)->get('page', 'classic', $home->id, 'home.hero_image')
        );
    }

    public function test_mixed_upload_stores_both_kinds(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.midia.store'), ['files' => [
                $this->fakeImage('a.jpg'),
                $this->fakeVideo('b.mp4'),
            ]])
            ->assertRedirect();

        $this->assertDatabaseHas('media', ['mime_type' => 'image/jpeg']);
        $this->assertDatabaseHas('media', ['mime_type' => 'video/mp4']);
    }
}
