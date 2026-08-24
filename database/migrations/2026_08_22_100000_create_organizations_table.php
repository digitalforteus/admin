<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', static function (Blueprint $Blueprint): void {
            $Blueprint->ulid('id')->primary()->comment('The unique identifier of the organization');
            $Blueprint->foreignUlid('enterprise_id')
                ->constrained()
                ->restrictOnDelete()
                ->comment('The enterprise the organization belongs to');
            $Blueprint->string('name')->comment('The organization name');
            $Blueprint->string('slug')->comment('The url segment the organization is addressed by, inside its enterprise');
            $Blueprint->string('icon')->nullable()->comment('The path of the icon the organization uploaded');
            $Blueprint->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('The user who created the organization, for display only');
            $Blueprint->timestamps();
            $Blueprint->unique(['enterprise_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
