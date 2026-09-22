<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // 0 = อาทิตย์ ... 6 = เสาร์ (ตรงกับ Carbon::dayOfWeek)
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');

            $table->unsignedSmallInteger('slot_duration_minutes')->default(60);
            $table->unsignedTinyInteger('capacity')->default(5);

            // ช่วงที่กฎนี้มีผล (null = ตลอดไป)
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'day_of_week', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_templates');
    }
};
