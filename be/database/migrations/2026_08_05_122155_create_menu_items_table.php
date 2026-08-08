<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            // Where the item renders: "header" (mega menu) or "footer".
            $table->string('location', 32);
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('opens_in_new_tab')->default(false);
            $table->timestamps();

            $table->index(['location', 'parent_id', 'sort_order']);
        });

        Schema::create('menu_item_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label');
            // Localised destination — the Vietnamese and English sites use
            // different paths for the same section (/dich-vu vs /services).
            $table->string('url')->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_translations');
        Schema::dropIfExists('menu_items');
    }
};
