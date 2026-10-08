<?php

namespace App\Modules\Auth\InstallSteps;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use App\Modules\Core\Contracts\InstallStep;

class CreateAdminUserStep implements InstallStep
{
    public function name(): string
    {
        return 'auth.admin_user';
    }

    public function order(): int
    {
        return 20;
    }

    public function run(array $context): void
    {
        $admin = $context['admin'] ?? [];

        if (($admin['email'] ?? '') === '') {
            throw new \RuntimeException('O contexto de instalação não contém "admin.email".');
        }

        User::query()->updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => (string) ($admin['name'] ?? 'Administrador'),
                'password' => (string) ($admin['password'] ?? ''),
                'role' => Role::Admin,
                'active' => true,
            ],
        );
    }
}
