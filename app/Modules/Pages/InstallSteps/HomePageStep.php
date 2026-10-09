<?php

namespace App\Modules\Pages\InstallSteps;

use App\Modules\Core\Contracts\InstallStep;
use App\Modules\Pages\Models\Page;

class HomePageStep implements InstallStep
{
    public function name(): string
    {
        return 'pages.home';
    }

    public function order(): int
    {
        return 30;
    }

    public function run(array $context): void
    {
        if (Page::query()->where('is_home', true)->exists()) {
            return;
        }

        $isEnglish = ($context['locale'] ?? 'pt_BR') === 'en';

        Page::firstOrCreate(
            ['slug' => $isEnglish ? 'home' : 'inicio'],
            [
                'title' => $isEnglish ? 'Home' : 'Início',
                'content' => ['body' => $isEnglish ? '<p>Welcome to your new site.</p>' : '<p>Bem-vindo ao seu novo site.</p>'],
                'status' => 'published',
                'is_home' => true,
                'published_at' => now(),
            ],
        );
    }
}
