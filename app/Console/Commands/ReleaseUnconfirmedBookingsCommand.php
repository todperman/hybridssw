<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

/**
 * คนที่ถูกเลื่อนจากคิวสำรองมีเวลายืนยันจำกัด
 * ถ้าปล่อยให้เงียบไว้ ที่นั่งจะถูกจองค้างโดยคนที่อาจไม่มา คนถัดไปในคิวก็ไม่ได้สิทธิ์
 */
class ReleaseUnconfirmedBookingsCommand extends Command
{
    protected $signature = 'bookings:release-unconfirmed {--dry-run : แสดงผลอย่างเดียว ไม่บันทึก}';

    protected $description = 'ปล่อยที่นั่งของคิวสำรองที่เลื่อนขึ้นแล้วไม่ยืนยันภายในเวลา';

    public function handle(BookingService $bookings): int
    {
        $expired = Booking::query()
            ->where('status', BookingStatus::Booked->value)
            ->whereNotNull('confirm_deadline_at')
            ->where('confirm_deadline_at', '<', now())
            ->whereHas('workoutSession', fn ($q) => $q->where('starts_at', '>', now()))
            ->with(['member.user', 'workoutSession'])
            ->get();

        if ($expired->isEmpty()) {
            $this->info('ไม่มีรายการที่เลยเวลายืนยัน');

            return self::SUCCESS;
        }

        foreach ($expired as $booking) {
            $this->line(sprintf(
                '%s %s (%s) เลยกำหนดยืนยัน %s',
                $this->option('dry-run') ? '[ทดลอง]' : '[ปล่อย]',
                $booking->reference,
                $booking->member->user->name ?? '-',
                $booking->confirm_deadline_at->format('d/m H:i'),
            ));

            if (! $this->option('dry-run')) {
                // ยกเลิกผ่าน service เพื่อให้คืนเครดิตและเลื่อนคิวถัดไปตามกติกาเดียวกัน
                $bookings->cancel($booking, null, 'ไม่ยืนยันสิทธิ์ภายในเวลาที่กำหนด');
            }
        }

        $this->info("รวม {$expired->count()} รายการ");

        return self::SUCCESS;
    }
}
