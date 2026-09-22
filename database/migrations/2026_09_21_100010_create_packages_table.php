<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('credits');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedSmallInteger('validity_days')->default(90);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('member_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();

            $table->string('package_name');
            $table->unsignedSmallInteger('credits_total');
            $table->unsignedSmallInteger('credits_used')->default(0);
            $table->decimal('price_paid', 10, 2)->default(0);

            $table->date('starts_at');
            $table->date('expires_at');
            // active | exhausted | expired | cancelled
            $table->string('status', 20)->default('active');

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_packages');
        Schema::dropIfExists('packages');
    }
};
