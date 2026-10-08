<?php

namespace Tests\Feature\Pages;

use App\Modules\Pages\InstallSteps\HomePageStep;
use App\Modules\Pages\InstallSteps\SampleContentStep;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\MenuItem;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallStepsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_step_creates_the_home_page(): void
    {
        (new HomePageStep)->run([]);

        $home = Page::where('is_home', true)->firstOrFail();

        $this->assertSame('inicio', $home->slug);
        $this->assertSame('published', $home->status);
    }

    public function test_sample_content_step_is_skipped_without_the_flag(): void
    {
        (new SampleContentStep)->run([]);

        $this->assertSame(0, Page::count());
        $this->assertSame(0, Menu::count());
    }

    public function test_sample_content_step_creates_pages_and_menu_with_the_flag(): void
    {
        (new HomePageStep)->run([]);
        (new SampleContentStep)->run(['sample_content' => true]);

        $this->assertSame(['inicio', 'sobre', 'contato'], Page::orderBy('id')->pluck('slug')->all());
        $this->assertSame('primary', Menu::firstOrFail()->location);
        $this->assertSame(['Início', 'Sobre', 'Contato'], MenuItem::orderBy('sort_order')->pluck('label')->all());
    }

    public function test_steps_are_idempotent(): void
    {
        foreach (range(1, 2) as $run) {
            (new HomePageStep)->run([]);
            (new SampleContentStep)->run(['sample_content' => true]);
        }

        $this->assertSame(3, Page::count());
        $this->assertSame(1, Menu::count());
        $this->assertSame(3, MenuItem::count());
    }
}
