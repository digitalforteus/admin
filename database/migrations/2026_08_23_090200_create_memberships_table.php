<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', static function (Blueprint $Blueprint): void {
            $Blueprint->string('subject_type', 32)->comment('The depth the standing is held at');
            $Blueprint->ulid('subject_id')->comment('The identifier of the subject the standing is held at');
            $Blueprint->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('The user holding the standing');
            $Blueprint->string('role')->comment('The standing the member holds');
            $Blueprint->timestamps();
            $Blueprint->primary(['subject_type', 'subject_id', 'user_id']);
            $Blueprint->index(['user_id', 'subject_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
