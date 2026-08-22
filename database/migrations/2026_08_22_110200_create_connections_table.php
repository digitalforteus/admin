<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', static function (Blueprint $Blueprint): void {
            $Blueprint->ulid('id')->primary()->comment('The unique identifier of the connection');
            $Blueprint->foreignUlid('enterprise_id')
                ->constrained()
                ->restrictOnDelete()
                ->comment('The enterprise that owns the credentials');
            $Blueprint->string('provider')->comment('The registry key of the plugin that serves the connection');
            $Blueprint->string('name')->comment('The connection name');
            $Blueprint->string('slug')->comment('The url segment the connection is addressed by');
            $Blueprint->text('credentials')->comment('The encrypted secrets the plugin declared');
            $Blueprint->json('config')->nullable()->comment('The setup values the plugin did not declare secret');
            $Blueprint->timestamps();
            $Blueprint->unique(['enterprise_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
