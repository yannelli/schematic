<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schematic_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('schematic_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('schematic_templates')
                ->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('content')->nullable();
            $table->json('fields')->nullable();
            $table->json('examples')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'slug']);
            $table->index(['template_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schematic_sections');
        Schema::dropIfExists('schematic_templates');
    }
};
