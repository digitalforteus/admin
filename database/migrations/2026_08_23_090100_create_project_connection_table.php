<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_connection', static function (Blueprint $Blueprint): void {
            $Blueprint->foreignUlid('project_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('The project that opted into the connection');
            $Blueprint->foreignUlid('connection_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('The connection the project opted into');
            $Blueprint->timestamp('enabled_at')->nullable()->comment('When the project enabled the connection');
            $Blueprint->timestamps();
            $Blueprint->primary(['project_id', 'connection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_connection');
    }
};
