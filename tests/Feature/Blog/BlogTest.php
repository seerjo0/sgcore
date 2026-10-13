<?php

namespace Tests\Feature\Blog;

use App\Models\User;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_public_archive_and_single_render_published_posts(): void
    {
        Post::factory()->published()->create(['title' => 'Olá mundo', 'slug' => 'ola-mundo']);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Olá mundo');

        $this->get(route('blog.show', 'ola-mundo'))
            ->assertOk()
            ->assertSee('Olá mundo');
    }

    public function test_draft_post_is_not_public_and_its_page_404s(): void
    {
        Post::factory()->create(['title' => 'Rascunho', 'slug' => 'rascunho', 'status' => 'draft']);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertDontSee('Rascunho');

        $this->get(route('blog.show', 'rascunho'))->assertNotFound();
    }

    public function test_future_scheduled_post_stays_hidden_until_published(): void
    {
        Post::factory()->create([
            'title' => 'Programado',
            'slug' => 'programado',
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('blog.index'))->assertOk()->assertDontSee('Programado');
        $this->get(route('blog.show', 'programado'))->assertNotFound();
    }

    public function test_archive_can_be_filtered_by_category(): void
    {
        $news = Category::factory()->create(['name' => 'Novidades', 'slug' => 'novidades']);
        Post::factory()->published()->create(['title' => 'Post de news', 'slug' => 'post-news', 'category_id' => $news->id]);
        Post::factory()->published()->create(['title' => 'Post solto', 'slug' => 'post-solto']);

        $this->get(route('blog.index', ['categoria' => 'novidades']))
            ->assertOk()
            ->assertSee('Post de news')
            ->assertDontSee('Post solto');
    }

    public function test_admin_can_publish_a_post_with_body_sanitized(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.posts.store'), [
                'title' => 'Meu post',
                'slug' => '',
                'status' => 'published',
                'excerpt' => 'Resumo curto',
                'content' => ['body' => '<p>Olá <strong>mundo</strong></p><script>alert(1)</script><a href="javascript:evil()">x</a>'],
            ])
            ->assertRedirect(route('admin.posts.index'));

        $post = Post::firstWhere('slug', 'meu-post');
        $this->assertNotNull($post);
        $this->assertSame($editor->id, $post->author_id);
        $this->assertStringContainsString('<strong>mundo</strong>', (string) $post->body());
        $this->assertStringNotContainsString('<script', (string) $post->body());
        $this->assertStringNotContainsString('javascript:', (string) $post->body());

        $this->get(route('blog.show', 'meu-post'))
            ->assertOk()
            ->assertSee('<strong>mundo</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_auto_generated_slug_gets_a_numeric_suffix_when_taken(): void
    {
        $editor = User::factory()->editor()->create();

        foreach (['Repetido', 'Repetido'] as $title) {
            $this->actingAs($editor)->post(route('admin.posts.store'), [
                'title' => $title,
                'slug' => '',
                'status' => 'draft',
            ]);
        }

        $this->assertSame(['repetido', 'repetido-2'], Post::orderBy('id')->pluck('slug')->all());
    }

    public function test_typed_slug_conflict_is_rejected_by_validation(): void
    {
        $editor = User::factory()->editor()->create();
        Post::factory()->create(['slug' => 'unico', 'title' => 'Existente']);

        $this->actingAs($editor)
            ->from(route('admin.posts.create'))
            ->post(route('admin.posts.store'), [
                'title' => 'Outro',
                'slug' => 'unico',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_title_and_status_are_required(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->from(route('admin.posts.create'))
            ->post(route('admin.posts.store'), ['title' => '', 'slug' => '', 'status' => ''])
            ->assertSessionHasErrors(['title', 'status']);
    }

    public function test_editor_can_update_and_delete_a_post(): void
    {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->create(['title' => 'Antes', 'slug' => 'antes']);

        $this->actingAs($editor)
            ->put(route('admin.posts.update', $post), [
                'title' => 'Depois',
                'slug' => 'depois',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.posts.index'));

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'slug' => 'depois', 'title' => 'Depois']);

        $this->actingAs($editor)
            ->delete(route('admin.posts.destroy', $post))
            ->assertRedirect(route('admin.posts.index'));

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_category_crud_and_post_linkage(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.categorias.store'), ['name' => 'Tutoriais', 'slug' => ''])
            ->assertRedirect(route('admin.categorias.index'));

        $category = Category::firstWhere('slug', 'tutoriais');
        $this->assertNotNull($category);

        $post = Post::factory()->published()->create(['category_id' => $category->id, 'slug' => 'no-cat', 'title' => 'Na categoria']);

        $this->actingAs($editor)->delete(route('admin.categorias.destroy', $category))->assertRedirect();

        $this->assertNull($post->fresh()->category_id);
    }

    public function test_guest_cannot_access_admin_and_reserved_slug_blocks_a_page(): void
    {
        $this->get(route('admin.posts.index'))->assertRedirect(route('login'));

        // 'blog' is reserved so a page cannot shadow the public blog route.
        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)
            ->from(route('admin.paginas.create'))
            ->post(route('admin.paginas.store'), ['title' => 'Blog', 'slug' => 'blog', 'status' => 'draft'])
            ->assertSessionHasErrors('slug');
    }

    public function test_post_belongs_to_a_category_and_author(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $post = Post::factory()->published()->create(['category_id' => $category->id, 'author_id' => $admin->id]);

        $this->assertSame($category->id, $post->category->id);
        $this->assertSame($admin->id, $post->author->id);
    }
}
