<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\MenuItem;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.menus.index'))->assertRedirect(route('login'));
    }

    public function test_editor_can_create_a_menu(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.menus.store'), ['title' => 'Principal', 'location' => 'primary'])
            ->assertRedirect();

        $this->assertDatabaseHas('menus', ['location' => 'primary']);
    }

    public function test_location_must_be_unique_and_known(): void
    {
        $admin = User::factory()->admin()->create();
        Menu::create(['title' => 'Topo', 'location' => 'primary']);

        $this->actingAs($admin)
            ->from(route('admin.menus.create'))
            ->post(route('admin.menus.store'), ['title' => 'Outro', 'location' => 'primary'])
            ->assertSessionHasErrors('location');

        $this->actingAs($admin)
            ->from(route('admin.menus.create'))
            ->post(route('admin.menus.store'), ['title' => 'Invalido', 'location' => 'sidebar'])
            ->assertSessionHasErrors('location');

        $this->assertSame(1, Menu::count());
    }

    public function test_add_page_and_custom_items(): void
    {
        $admin = User::factory()->admin()->create();
        $menu = Menu::create(['title' => 'Principal', 'location' => 'primary']);
        $page = Page::create(['title' => 'Sobre', 'slug' => 'sobre', 'status' => 'published']);

        $this->actingAs($admin)
            ->post(route('admin.menus.itens.store', $menu), [
                'label' => 'Sobre nós',
                'type' => 'page',
                'page_id' => $page->id,
            ])
            ->assertRedirect(route('admin.menus.edit', $menu));

        $this->actingAs($admin)
            ->post(route('admin.menus.itens.store', $menu), [
                'label' => 'Blog externo',
                'type' => 'custom',
                'url' => 'https://exemplo.com/blog',
            ]);

        $items = $menu->items()->get();

        $this->assertSame([0, 1], $items->pluck('sort_order')->all());
        $this->assertSame($page->id, $items[0]->page_id);
        $this->assertNull($items[0]->url);
        $this->assertNull($items[1]->page_id);
        $this->assertSame('https://exemplo.com/blog', $items[1]->url);
    }

    public function test_item_validation_is_type_specific(): void
    {
        $admin = User::factory()->admin()->create();
        $menu = Menu::create(['title' => 'Principal', 'location' => 'primary']);

        $this->actingAs($admin)
            ->from(route('admin.menus.edit', $menu))
            ->post(route('admin.menus.itens.store', $menu), ['label' => 'Sem página', 'type' => 'page'])
            ->assertSessionHasErrors('page_id');

        $this->actingAs($admin)
            ->from(route('admin.menus.edit', $menu))
            ->post(route('admin.menus.itens.store', $menu), ['label' => 'Sem url', 'type' => 'custom'])
            ->assertSessionHasErrors('url');

        $this->actingAs($admin)
            ->from(route('admin.menus.edit', $menu))
            ->post(route('admin.menus.itens.store', $menu), [
                'label' => 'Ancora errada',
                'type' => 'anchor',
                'url' => 'secao',
            ])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, $menu->items()->count());
    }

    public function test_items_can_be_nested_under_a_root_item_only(): void
    {
        $admin = User::factory()->admin()->create();
        $menu = Menu::create(['title' => 'Principal', 'location' => 'primary']);
        $other = Menu::create(['title' => 'Rodapé', 'location' => 'footer']);

        $root = MenuItem::create(['menu_id' => $menu->id, 'label' => 'Pai', 'type' => 'custom', 'url' => '/x', 'sort_order' => 0]);
        $child = MenuItem::create(['menu_id' => $menu->id, 'label' => 'Filho', 'type' => 'custom', 'url' => '/y', 'parent_id' => $root->id, 'sort_order' => 1]);
        $foreign = MenuItem::create(['menu_id' => $other->id, 'label' => 'De outro', 'type' => 'custom', 'url' => '/z', 'sort_order' => 0]);

        // Nest a second item under the root.
        $this->actingAs($admin)
            ->post(route('admin.menus.itens.store', $menu), [
                'label' => 'Filho 2',
                'type' => 'custom',
                'url' => '/w',
                'parent_id' => $root->id,
            ]);

        $this->assertSame(2, $root->children()->count());

        // Parent must be a root item of the same menu.
        $this->actingAs($admin)
            ->from(route('admin.menus.edit', $menu))
            ->post(route('admin.menus.itens.store', $menu), [
                'label' => 'Profundo',
                'type' => 'custom',
                'url' => '/deep',
                'parent_id' => $child->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($admin)
            ->from(route('admin.menus.edit', $menu))
            ->post(route('admin.menus.itens.store', $menu), [
                'label' => 'Estrangeiro',
                'type' => 'custom',
                'url' => '/foreign',
                'parent_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_move_item_reorders_only_its_sibling_group(): void
    {
        $admin = User::factory()->admin()->create();
        $menu = Menu::create(['title' => 'Principal', 'location' => 'primary']);

        $items = collect(['Um', 'Dois', 'Três'])->map(fn (string $label, int $index): MenuItem => MenuItem::create([
            'menu_id' => $menu->id,
            'label' => $label,
            'type' => 'custom',
            'url' => '/'.$index,
            'sort_order' => $index,
        ]));

        [$first, $second, $third] = $items->all();

        $this->actingAs($admin)
            ->post(route('admin.menus.itens.mover', [$menu, $third]), ['direcao' => 'up'])
            ->assertRedirect(route('admin.menus.edit', $menu));

        $this->assertSame(
            [$first->id, $third->id, $second->id],
            $menu->rootItems()->pluck('id')->all(),
        );

        $this->actingAs($admin)
            ->post(route('admin.menus.itens.mover', [$menu, $third]), ['direcao' => 'down']);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $menu->rootItems()->pluck('id')->all(),
        );
    }

    public function test_deleting_an_item_cascades_its_children(): void
    {
        $admin = User::factory()->admin()->create();
        $menu = Menu::create(['title' => 'Principal', 'location' => 'primary']);
        $root = MenuItem::create(['menu_id' => $menu->id, 'label' => 'Pai', 'type' => 'custom', 'url' => '/x', 'sort_order' => 0]);
        $child = MenuItem::create(['menu_id' => $menu->id, 'label' => 'Filho', 'type' => 'custom', 'url' => '/y', 'parent_id' => $root->id, 'sort_order' => 1]);

        $this->actingAs($admin)
            ->delete(route('admin.menus.itens.destroy', [$menu, $root]))
            ->assertRedirect(route('admin.menus.edit', $menu));

        $this->assertDatabaseMissing('menu_items', ['id' => $root->id]);
        $this->assertDatabaseMissing('menu_items', ['id' => $child->id]);
    }

    public function test_deleting_a_menu_cascades_its_items(): void
    {
        $admin = User::factory()->admin()->create();
        $menu = Menu::create(['title' => 'Principal', 'location' => 'primary']);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Item', 'type' => 'custom', 'url' => '/x', 'sort_order' => 0]);

        $this->actingAs($admin)
            ->delete(route('admin.menus.destroy', $menu))
            ->assertRedirect(route('admin.menus.index'));

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertDatabaseCount('menu_items', 0);
    }
}
