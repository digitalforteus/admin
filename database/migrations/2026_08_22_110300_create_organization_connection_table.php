<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_connection', static function (Blueprint $Blueprint): void {
            $Blueprint->foreignUlid('organization_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('The organization that opted into the connection');
            $Blueprint->foreignUlid('connection_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('The connection the organization opted into');
            $Blueprint->timestamp('enabled_at')->nullable()->comment('When the organization enabled the connection');
            $Blueprint->timestamps();
            $Blueprint->primary(['organization_id', 'connection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_connection');
    }
};
