<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', static function (Blueprint $Blueprint): void {
            $Blueprint->id();
            $Blueprint->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $Blueprint->string('name');
            $Blueprint->string('credential_id')->unique();
            $Blueprint->json('credential');
            $Blueprint->timestamp('last_used_at')->nullable();
            $Blueprint->timestamps();

            $Blueprint->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
