<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            $table->timestamp('password_changed_at')->nullable()->after('app_authentication_recovery_codes');
        });

        // Quem já existe conta a partir da última alteração da conta.
        DB::table('users')->whereNull('password_changed_at')->update(['password_changed_at' => DB::raw('updated_at')]);

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('pii_purged_at')->nullable()->after('status_manual');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('pii_purged_at'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'password_changed_at']));
    }
};
