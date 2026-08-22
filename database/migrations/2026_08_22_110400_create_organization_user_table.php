<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_user', static function (Blueprint $Blueprint): void {
            $Blueprint->foreignUlid('organization_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('The organization the membership is held in');
            $Blueprint->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('The user holding the membership');
            $Blueprint->string('role')->comment('The role the member holds in the organization');
            $Blueprint->timestamps();
            $Blueprint->primary(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
