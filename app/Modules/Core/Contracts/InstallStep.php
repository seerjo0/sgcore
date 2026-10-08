<?php

namespace App\Modules\Core\Contracts;

interface InstallStep
{
    /**
     * Stable identifier of the step (e.g. "auth.admin_user").
     */
    public function name(): string;

    /**
     * Execution order; lower values run first.
     */
    public function order(): int;

    /**
     * Execute the step using the shared installation context.
     *
     * @param  array<string, mixed>  $context
     */
    public function run(array $context): void;
}
