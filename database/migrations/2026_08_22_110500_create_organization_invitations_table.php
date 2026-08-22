<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_invitations', static function (Blueprint $Blueprint): void {
            $Blueprint->ulid('id')->primary()->comment('The unique identifier of the invitation');
            $Blueprint->foreignUlid('organization_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('The organization the invitation grants membership of');
            $Blueprint->string('email')->comment('The address the invitation was sent to');
            $Blueprint->string('role')->comment('The role the invitation grants on acceptance');
            $Blueprint->string('token')->unique()->comment('The secret the acceptance link carries');
            $Blueprint->timestamp('expires_at')->comment('When the invitation stops being acceptable');
            $Blueprint->foreignUlid('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('The user who sent the invitation');
            $Blueprint->timestamps();
            $Blueprint->unique(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
