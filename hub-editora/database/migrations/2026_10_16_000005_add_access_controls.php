<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controles de acesso exigidos pela Amazon (Política de Proteção de Dados):
 * perfis com menor privilégio, bloqueio após tentativas falhas, desativação
 * de contas sem uso e histórico das últimas senhas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('expedicao')->after('email'); // admin | expedicao
            $table->unsignedSmallInteger('failed_logins')->default(0)->after('password_changed_at');
            $table->timestamp('locked_until')->nullable()->after('failed_logins');
            $table->timestamp('disabled_at')->nullable()->after('locked_until');
            $table->timestamp('last_login_at')->nullable()->after('disabled_at');
        });

        // Quem já usa o painel hoje administra tudo; os novos entram como Expedição.
        DB::table('users')->update(['role' => 'admin']);

        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'failed_logins', 'locked_until', 'disabled_at', 'last_login_at']));
    }
};
