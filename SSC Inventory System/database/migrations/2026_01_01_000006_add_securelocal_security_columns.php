<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('mfa_enabled')->default(false)->after('is_active');
            $table->text('mfa_secret')->nullable()->after('mfa_enabled');
            $table->timestamp('mfa_confirmed_at')->nullable()->after('mfa_secret');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('mfa_confirmed_at');
            $table->timestamp('locked_until')->nullable()->index()->after('failed_login_attempts');
            $table->timestamp('privacy_consent_at')->nullable()->after('locked_until');
            $table->string('privacy_consent_version', 20)->nullable()->after('privacy_consent_at');
        });

        Schema::create('user_mfa_backup_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('borrowing_requests', function (Blueprint $table) {
            $table->text('borrower_contact')->nullable()->after('department_organization');
            $table->boolean('count_verified')->default(false)->after('remarks');
        });

        Schema::table('return_transactions', function (Blueprint $table) {
            $table->boolean('count_verified')->default(false)->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('return_transactions', function (Blueprint $table) {
            $table->dropColumn('count_verified');
        });

        Schema::table('borrowing_requests', function (Blueprint $table) {
            $table->dropColumn(['borrower_contact', 'count_verified']);
        });

        Schema::dropIfExists('user_mfa_backup_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mfa_enabled',
                'mfa_secret',
                'mfa_confirmed_at',
                'failed_login_attempts',
                'locked_until',
                'privacy_consent_at',
                'privacy_consent_version',
            ]);
        });
    }
};
