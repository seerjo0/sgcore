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

        Page::create([
            'title' => 'Início',
            'slug' => 'inicio',
            'content' => ['body' => '<p>Bem-vindo ao seu novo site.</p>'],
            'status' => 'published',
            'is_home' => true,
            'published_at' => now(),
        ]);
    }
}
