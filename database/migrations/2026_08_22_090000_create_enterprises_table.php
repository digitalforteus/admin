<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprises', static function (Blueprint $Blueprint): void {
            $Blueprint->ulid('id')->primary()->comment('The unique identifier of the enterprise');
            $Blueprint->string('name')->comment('The enterprise name');
            $Blueprint->string('slug')->unique()->comment('The url segment the enterprise is addressed by');
            $Blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprises');
    }
};
