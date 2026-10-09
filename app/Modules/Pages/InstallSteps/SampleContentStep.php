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

        $isEnglish = ($context['locale'] ?? 'pt_BR') === 'en';

        Page::firstOrCreate(
            ['slug' => $isEnglish ? 'about' : 'sobre'],
            [
                'title' => $isEnglish ? 'About' : 'Sobre',
                'content' => ['body' => $isEnglish
                    ? '<p>Tell a little about your company or project.</p>'
                    : '<p>Conte um pouco sobre a sua empresa ou projeto.</p>'],
                'status' => 'published',
                'published_at' => now(),
            ],
        );

        Page::firstOrCreate(
            ['slug' => $isEnglish ? 'contact' : 'contato'],
            [
                'title' => $isEnglish ? 'Contact' : 'Contato',
                'content' => ['body' => $isEnglish
                    ? '<p>Reach us through the form or the contact e-mail.</p>'
                    : '<p>Escreva para nós usando o formulário ou o e-mail de contato.</p>'],
                'status' => 'published',
                'published_at' => now(),
            ],
        );

        $menu = Menu::firstOrCreate(
            ['location' => 'primary'],
            ['title' => $isEnglish ? 'Main' : 'Principal'],
        );

        $links = [
            ['label' => $isEnglish ? 'Home' : 'Início', 'page' => Page::where('is_home', true)->first() ?? Page::where('slug', $isEnglish ? 'home' : 'inicio')->first()],
            ['label' => $isEnglish ? 'About' : 'Sobre', 'page' => Page::where('slug', $isEnglish ? 'about' : 'sobre')->first()],
            ['label' => $isEnglish ? 'Contact' : 'Contato', 'page' => Page::where('slug', $isEnglish ? 'contact' : 'contato')->first()],
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
