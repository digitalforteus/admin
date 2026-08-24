<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', static function (Blueprint $Blueprint): void {
            $Blueprint->ulid('id')->primary()->comment('The unique identifier of the project');
            $Blueprint->foreignUlid('organization_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('The organization the project belongs to');
            $Blueprint->string('name')->comment('The project name');
            $Blueprint->string('slug')->comment('The url segment the project is addressed by, inside its organization');
            $Blueprint->string('icon')->nullable()->comment('The path of the icon the project uploaded');
            $Blueprint->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('The user who created the project, for display only');
            $Blueprint->timestamps();
            $Blueprint->unique(['organization_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
