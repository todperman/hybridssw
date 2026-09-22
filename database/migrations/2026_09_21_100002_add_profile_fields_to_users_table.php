<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            $table->string('role', 20)->default('member')->after('email');
            $table->string('phone', 30)->nullable()->after('role');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('line_user_id', 64)->nullable()->unique()->after('avatar_path');
            $table->boolean('is_active')->default(true)->after('line_user_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();

            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['role', 'is_active']);
            $table->dropColumn([
                'branch_id', 'role', 'phone', 'avatar_path',
                'line_user_id', 'is_active', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
