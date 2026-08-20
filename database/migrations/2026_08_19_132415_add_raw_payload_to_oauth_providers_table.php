<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::whenTableDoesntHaveColumn('oauth_providers', 'payload', static function () {
            Schema::table('oauth_providers', static function (Blueprint $Blueprint) {
                $Blueprint->json('payload')
                    ->nullable()
                    ->comment('The complete raw JSON payload from the OAuth provider for debugging and data recovery');
            });
        });
    }

    public function down(): void
    {
        Schema::table('oauth_providers', static function (Blueprint $Blueprint) {
            $Blueprint->dropColumn('payload');
        });
    }
};
