<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual public ordering of galleries (Aparência/temas e módulos que as
     * listam exibem nesta ordem). Sem o column, "público" = mais recente
     * primeiro; o seeding preserva exatamente essa sequência.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('title');
        });

        // ordem pública atual: mais recente primeiro; empate = maior id antes
        DB::table('galleries')->orderByDesc('created_at')->orderByDesc('id')
            ->pluck('id')
            ->each(function (int $id, int $position): void {
                DB::table('galleries')->where('id', $id)->update(['sort_order' => $position + 1]);
            });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
