<?php

namespace Tests\Feature\Media;

use App\Models\User;
use App\Modules\Media\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ordem de exibição das galerias (v1.9): setas ↑/↓ no painel, coluna
 * sort_order, novas galerias entram no topo e tudo que lista galerias
 * (index do admin, seletor de slots, módulos) segue essa ordem.
 */
class GalleryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function titlesOnPage(): array
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.galerias.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<td class="px-4 py-3 font-medium">([^<]*)<\/td>/', $html, $m);

        return $m[1] ?? [];
    }

    public function test_new_gallery_enters_first(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.galerias.store'), ['title' => 'Alpha']);
        $this->actingAs($admin)->post(route('admin.galerias.store'), ['title' => 'Beta']);

        $this->assertSame([
            ['title' => 'Beta', 'sort_order' => 1],
            ['title' => 'Alpha', 'sort_order' => 2],
        ], Gallery::query()->ordered()->get(['title', 'sort_order'])->map(fn (Gallery $g): array => [
            'title' => $g->title, 'sort_order' => (int) $g->sort_order,
        ])->all());
    }

    public function test_index_lists_in_display_order_with_arrows(): void
    {
        $admin = $this->admin();

        foreach (['Gamma', 'Alpha', 'Beta'] as $title) {
            $this->actingAs($admin)->post(route('admin.galerias.store'), ['title' => $title]);
        }

        // cada store entra no topo: Beta, Alpha, Gamma
        $this->assertSame(['Beta', 'Alpha', 'Gamma'], $this->titlesOnPage());

        $html = $this->actingAs($admin)->get(route('admin.galerias.index'))->assertOk()->getContent();
        $this->assertStringContainsString('galerias/'.Gallery::query()->ordered()->first()->id.'/mover', $html);
        $this->assertStringContainsString('Mover para cima', $html);
        $this->assertStringContainsString('Mover para baixo', $html);
    }

    public function test_move_down_swaps_with_next(): void
    {
        $admin = $this->admin();

        foreach (['Um', 'Dois', 'Tres'] as $title) {
            $this->actingAs($admin)->post(route('admin.galerias.store'), ['title' => $title]);
        }

        $primeira = Gallery::query()->ordered()->first();

        $this->actingAs($admin)
            ->post(route('admin.galerias.mover', $primeira), ['direcao' => 'down'])
            ->assertRedirect(route('admin.galerias.index'))
            ->assertSessionHas('success');

        // stores pré-põem: ordem era Tres, Dois, Um; descer o topo troca com Dois
        $this->assertSame(['Dois', 'Tres', 'Um'], $this->titlesOnPage());
    }

    public function test_move_up_swaps_with_previous(): void
    {
        $admin = $this->admin();

        foreach (['A', 'B', 'C'] as $title) {
            $this->actingAs($admin)->post(route('admin.galerias.store'), ['title' => $title]);
        }

        $ultima = Gallery::query()->ordered()->get()->last();

        $this->actingAs($admin)
            ->post(route('admin.galerias.mover', $ultima), ['direcao' => 'up'])
            ->assertSessionHas('success');

        // ordem pós-stores: C, B, A; subir A troca com B → C, A, B
        $this->assertSame(['C', 'A', 'B'], $this->titlesOnPage());
    }

    public function test_move_at_boundaries_is_a_noop(): void
    {
        $admin = $this->admin();

        foreach (['X', 'Y'] as $title) {
            $this->actingAs($admin)->post(route('admin.galerias.store'), ['title' => $title]);
        }

        $topo = Gallery::query()->ordered()->first();

        $this->actingAs($admin)
            ->post(route('admin.galerias.mover', $topo), ['direcao' => 'up'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Y', 'X'], $this->titlesOnPage());
    }

    public function test_invalid_direction_is_ignored(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.galerias.store'), ['title' => 'Unica']);

        $gallery = Gallery::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.galerias.mover', $gallery), ['direcao' => 'esquerda']);

        $this->assertSame(1, (int) $gallery->fresh()->sort_order);
    }

    public function test_migration_seeded_existing_rows_newest_first(): void
    {
        // colunas de ordem com 0 (linhas antigas) ainda listam estáveis:
        // empate → maior id primeiro (comportamento histórico).
        Gallery::query()->insert([
            ['title' => 'Velha', 'created_at' => now()->subDays(5), 'updated_at' => now(), 'sort_order' => 0],
            ['title' => 'Nova', 'created_at' => now(), 'updated_at' => now(), 'sort_order' => 0],
        ]);

        $this->assertSame(['Nova', 'Velha'], Gallery::query()->ordered()->pluck('title')->all());
    }

    public function test_editor_can_move_galleries_too(): void
    {
        // manage-media libera admin E editor (mesma regra do resto do módulo)
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('admin.galerias.store'), ['title' => 'Do Editor']);
        $this->actingAs($editor)->post(route('admin.galerias.store'), ['title' => 'Também do Editor']);

        $topo = Gallery::query()->ordered()->first();

        $this->actingAs($editor)
            ->post(route('admin.galerias.mover', $topo), ['direcao' => 'down'])
            ->assertRedirect(route('admin.galerias.index'))
            ->assertSessionHas('success');

        $this->assertSame(['Do Editor', 'Também do Editor'], Gallery::query()->ordered()->pluck('title')->all());
    }

    public function test_guest_cannot_move_galleries(): void
    {
        $gallery = Gallery::factory()->create();

        $this->post(route('admin.galerias.mover', $gallery), ['direcao' => 'up'])
            ->assertRedirect(route('login'));
    }
}
