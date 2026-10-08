<?php

namespace App\Modules\Pages\InstallSteps;

use App\Modules\Core\Contracts\InstallStep;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\MenuItem;
use App\Modules\Pages\Models\Page;

class SampleContentStep implements InstallStep
{
    public function name(): string
    {
        return 'pages.sample_content';
    }

    public function order(): int
    {
        return 40;
    }

    public function run(array $context): void
    {
        if (! (bool) ($context['sample_content'] ?? false)) {
            return;
        }

        Page::firstOrCreate(
            ['slug' => 'sobre'],
            [
                'title' => 'Sobre',
                'content' => ['body' => '<p>Conte um pouco sobre a sua empresa ou projeto.</p>'],
                'status' => 'published',
                'published_at' => now(),
            ],
        );

        Page::firstOrCreate(
            ['slug' => 'contato'],
            [
                'title' => 'Contato',
                'content' => ['body' => '<p>Escreva para nós usando o formulário ou o e-mail de contato.</p>'],
                'status' => 'published',
                'published_at' => now(),
            ],
        );

        $menu = Menu::firstOrCreate(
            ['location' => 'primary'],
            ['title' => 'Principal'],
        );

        $links = [
            ['label' => 'Início', 'page' => Page::where('is_home', true)->first() ?? Page::where('slug', 'inicio')->first()],
            ['label' => 'Sobre', 'page' => Page::where('slug', 'sobre')->first()],
            ['label' => 'Contato', 'page' => Page::where('slug', 'contato')->first()],
        ];

        $sort = 0;

        foreach ($links as $link) {
            if ($link['page'] === null) {
                continue;
            }

            MenuItem::firstOrCreate(
                ['menu_id' => $menu->id, 'label' => $link['label']],
                [
                    'type' => 'page',
                    'page_id' => $link['page']->id,
                    'sort_order' => $sort++,
                ],
            );
        }
    }
}
