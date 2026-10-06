<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

/**
 * คืนที่นั่งที่ถูกถือค้างไว้โดยไม่มีใครใช้
 *
 * 1. คนที่ถูกเลื่อนจากคิวสำรองมีเวลายืนยันจำกัด ถ้าปล่อยให้เงียบไว้
 *    ที่นั่งจะถูกจองค้างโดยคนที่อาจไม่มา คนถัดไปในคิวก็ไม่ได้สิทธิ์
 * 2. คำขอที่รออนุมัติจนรอบเริ่มไปแล้ว ไม่มีทางได้รับอนุมัติอีก ปิดทิ้งให้สมาชิกเห็นสถานะจริง
 */
class ReleaseUnconfirmedBookingsCommand extends Command
{
    protected $signature = 'bookings:release-unconfirmed {--dry-run : แสดงผลอย่างเดียว ไม่บันทึก}';

    protected $description = 'ปล่อยที่นั่งของคิวสำรองที่ไม่ยืนยัน และคำขอที่รออนุมัติจนรอบเริ่ม';

    public function handle(BookingService $bookings): int
    {
        $this->expireUnapproved($bookings);

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

    protected function expireUnapproved(BookingService $bookings): void
    {
        $stale = Booking::query()
            ->where('status', BookingStatus::Pending->value)
            ->whereHas('workoutSession', fn ($q) => $q->where('starts_at', '<=', now()))
            ->get();

        foreach ($stale as $booking) {
            $this->line(sprintf(
                '%s %s รอบเริ่มแล้วแต่ยังไม่ได้รับการอนุมัติ',
                $this->option('dry-run') ? '[ทดลอง]' : '[ปิด]',
                $booking->reference,
            ));

            $this->option('dry-run') || $bookings->expireUnapproved($booking);
        }
    }
}
