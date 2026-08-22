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
            $Blueprint->string('name')->comment('The organization name');
            $Blueprint->string('icon')->nullable()->comment('The path of the icon the organization uploaded');
            $Blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
