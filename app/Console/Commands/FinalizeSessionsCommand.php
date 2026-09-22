<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Models\WorkoutSession;
use App\Services\BookingService;
use Illuminate\Console\Command;

/**
 * ปิดรอบที่จบไปแล้ว: คนเช็คอินถือว่ามาเล่นแล้ว คนที่ไม่เช็คอินถือว่าไม่มาตามนัด
 * การนับ no-show อัตโนมัติคือสิ่งที่ทำให้ที่นั่งไม่ถูกจองทิ้งไว้เฉยๆ
 */
class FinalizeSessionsCommand extends Command
{
    protected $signature = 'sessions:finalize
        {--grace=30 : รอกี่นาทีหลังรอบจบก่อนสรุปผล}
        {--dry-run : แสดงผลอย่างเดียว ไม่บันทึก}';

    protected $description = 'สรุปผลรอบที่จบแล้วและบันทึกผู้ที่ไม่มาตามนัด';

    public function handle(BookingService $bookings): int
    {
        $cutoff = now()->subMinutes((int) $this->option('grace'));
        $dry = $this->option('dry-run');

        $sessions = WorkoutSession::query()
            ->whereIn('status', [SessionStatus::Open->value, SessionStatus::Closed->value])
            ->where('ends_at', '<', $cutoff)
            ->with('bookings.member')
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('ไม่มีรอบที่ต้องสรุปผล');

            return self::SUCCESS;
        }

        $completed = 0;
        $noShows = 0;

        foreach ($sessions as $session) {
            foreach ($session->bookings as $booking) {
                if ($booking->status === BookingStatus::CheckedIn) {
                    $dry || $booking->update(['status' => BookingStatus::Completed]);
                    $completed++;

                    continue;
                }

                if ($booking->status === BookingStatus::Booked) {
                    $dry || $bookings->markNoShow($booking);
                    $noShows++;

                    continue;
                }

                // คิวสำรองที่ไม่เคยได้ที่นั่งถือว่าจบไปเฉยๆ ไม่นับเป็นความผิด
                if ($booking->status === BookingStatus::Waitlisted) {
                    $dry || $booking->update([
                        'status' => BookingStatus::Cancelled,
                        'waitlist_position' => null,
                        'cancelled_at' => now(),
                        'cancellation_reason' => 'รอบจบโดยไม่ได้เลื่อนขึ้นจากคิวสำรอง',
                    ]);
                }
            }

            $dry || $session->update(['status' => SessionStatus::Completed]);
        }

        $prefix = $dry ? '[ทดลอง] ' : '';
        $this->info("{$prefix}ปิดรอบ {$sessions->count()} รอบ | มาเล่นจริง {$completed} | ไม่มาตามนัด {$noShows}");

        return self::SUCCESS;
    }
}
