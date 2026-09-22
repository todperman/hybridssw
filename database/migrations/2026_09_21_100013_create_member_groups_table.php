<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // เพดานจำนวนลูกทีมต่อกลุ่ม เป็นค่าที่แอดมินกำหนด เทรนเนอร์แก้ไม่ได้
            $table->unsignedTinyInteger('max_group_size')->default(5)->after('default_capacity');
        });

        Schema::create('member_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->string('color', 20)->default('brand');

            // ค่าเฉพาะกลุ่มที่แอดมินตั้งทับได้ null = ใช้เพดานของสาขา
            $table->unsignedTinyInteger('max_members')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['trainer_id', 'name']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('member_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['member_group_id', 'member_id']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_group_members');
        Schema::dropIfExists('member_groups');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('max_group_size');
        });
    }
};
