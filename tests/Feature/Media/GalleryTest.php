<?php

namespace Tests\Feature\Media;

use App\Models\User;
use App\Modules\Media\Models\Gallery;
use App\Modules\Media\Models\GalleryItem;
use App\Modules\Media\Models\Medium;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
        Storage::fake('local');
    }

    public function test_guest_is_redirected_to_login_from_galleries(): void
    {
        $this->get(route('admin.galerias.index'))->assertRedirect(route('login'));
    }

    public function test_editor_can_create_gallery(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.galerias.store'), ['title' => 'Viagem 2026'])
            ->assertRedirect();

        $this->assertDatabaseHas('galleries', ['title' => 'Viagem 2026']);
    }

    public function test_gallery_title_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.galerias.create'))
            ->post(route('admin.galerias.store'), ['title' => ''])
            ->assertRedirect(route('admin.galerias.create'))
            ->assertSessionHasErrors('title');
    }

    public function test_add_media_to_gallery_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $gallery = Gallery::factory()->create();
        $first = Medium::factory()->create();
        $second = Medium::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.galerias.itens.store', $gallery), ['media_ids' => [$first->id]])
            ->assertRedirect(route('admin.galerias.edit', $gallery));

        $this->actingAs($admin)
            ->post(route('admin.galerias.itens.store', $gallery), ['media_ids' => [$first->id]])
            ->assertRedirect(route('admin.galerias.edit', $gallery));

        $this->assertSame(1, $gallery->items()->count());

        $this->actingAs($admin)
            ->post(route('admin.galerias.itens.store', $gallery), ['media_ids' => [$first->id, $second->id]])
            ->assertRedirect(route('admin.galerias.edit', $gallery));

        $this->assertSame(2, $gallery->items()->count());
    }

    public function test_add_item_validates_media_existence(): void
    {
        $admin = User::factory()->admin()->create();
        $gallery = Gallery::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.galerias.itens.store', $gallery), ['media_ids' => [999999]])
            ->assertSessionHasErrors('media_ids.0');
    }

    public function test_update_item_caption_is_scoped_to_the_gallery(): void
    {
        $admin = User::factory()->admin()->create();
        $galleryA = Gallery::factory()->create();
        $galleryB = Gallery::factory()->create();
        $item = GalleryItem::create([
            'gallery_id' => $galleryA->id,
            'media_id' => Medium::factory()->create()->id,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.galerias.itens.update', [$galleryB, $item]), ['caption' => 'hack'])
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('admin.galerias.itens.update', [$galleryA, $item]), ['caption' => 'Legenda boa'])
            ->assertRedirect(route('admin.galerias.edit', $galleryA));

        $this->assertDatabaseHas('gallery_items', ['id' => $item->id, 'caption' => 'Legenda boa']);
    }

    public function test_move_item_reorders_gallery(): void
    {
        $admin = User::factory()->admin()->create();
        $gallery = Gallery::factory()->create();

        $media = Medium::factory()->count(3)->create();

        foreach ($media as $position => $medium) {
            GalleryItem::create([
                'gallery_id' => $gallery->id,
                'media_id' => $medium->id,
                'sort_order' => $position,
            ]);
        }

        [$first, $second, $third] = $media->all();

        $this->actingAs($admin)
            ->post(route('admin.galerias.itens.mover', [$gallery, $third->id]), ['direcao' => 'up'])
            ->assertRedirect(route('admin.galerias.edit', $gallery));

        $this->assertSame(
            [$first->id, $third->id, $second->id],
            $gallery->items()->pluck('media_id')->all(),
        );

        $thirdItem = $gallery->items()->where('media_id', $third->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.galerias.itens.mover', [$gallery, $thirdItem->id]), ['direcao' => 'down'])
            ->assertRedirect(route('admin.galerias.edit', $gallery));

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $gallery->items()->pluck('media_id')->all(),
        );
    }

    public function test_delete_item_keeps_media(): void
    {
        $admin = User::factory()->admin()->create();
        $gallery = Gallery::factory()->create();
        $medium = Medium::factory()->create();
        $item = GalleryItem::create([
            'gallery_id' => $gallery->id,
            'media_id' => $medium->id,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.galerias.itens.destroy', [$gallery, $item]))
            ->assertRedirect(route('admin.galerias.edit', $gallery));

        $this->assertDatabaseMissing('gallery_items', ['id' => $item->id]);
        $this->assertDatabaseHas('media', ['id' => $medium->id]);
    }

    public function test_delete_gallery_cascades_items_but_keeps_media(): void
    {
        $admin = User::factory()->admin()->create();
        $gallery = Gallery::factory()->create();
        $medium = Medium::factory()->create();

        GalleryItem::create([
            'gallery_id' => $gallery->id,
            'media_id' => $medium->id,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.galerias.destroy', $gallery))
            ->assertRedirect(route('admin.galerias.index'));

        $this->assertDatabaseMissing('galleries', ['id' => $gallery->id]);
        $this->assertDatabaseCount('gallery_items', 0);
        $this->assertDatabaseHas('media', ['id' => $medium->id]);
    }
}
