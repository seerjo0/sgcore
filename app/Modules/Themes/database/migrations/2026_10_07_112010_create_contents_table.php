<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20);                 // global | theme
            $table->string('theme', 100)->default('');   // vazio para escopo global
            $table->unsignedBigInteger('page_id')->default(0); // 0 = site inteiro (home sem página)
            $table->json('values');                      // map slot_id => valor
            $table->timestamps();

            $table->unique(['scope', 'theme', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
