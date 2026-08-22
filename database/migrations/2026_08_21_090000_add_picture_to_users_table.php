<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::whenTableDoesntHaveColumn('users', 'picture', static function () {
            Schema::table('users', static function (Blueprint $Blueprint) {
                $Blueprint->string('picture')->nullable()->after('phone')->comment('The path of the profile picture the user uploaded');
            });
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $Blueprint) {
            $Blueprint->dropColumn('picture');
        });
    }
};
