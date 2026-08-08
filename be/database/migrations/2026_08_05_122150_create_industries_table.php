<?php

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('industries')->cascadeOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default(ContentStatus::Draft->value);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('industry_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps();

            $table->unique(['industry_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        // Cross-links an industry to the services offered for it, the way the
        // reference site surfaces "services for this industry" and vice versa.
        Schema::create('industry_service', function (Blueprint $table): void {
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['industry_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_service');
        Schema::dropIfExists('industry_translations');
        Schema::dropIfExists('industries');
    }
};
