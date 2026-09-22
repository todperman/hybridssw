<?php

namespace App\Console\Commands;

use App\Exceptions\BookingException;
use App\Models\Member;
use App\Models\Trainer;
use App\Models\WorkoutSession;
use App\Services\BookingService;
use Illuminate\Console\Command;

/**
 * ใช้ตรวจว่าการจองพร้อมกันหลายโปรเซสจะไม่ทะลุ capacity
 * ทุกโปรเซสรอจนถึงเวลา --at เดียวกันแล้วค่อยยิงพร้อมกัน เพื่อให้ชนกันจริง
 */
class StressBookCommand extends Command
{
    protected $signature = 'booking:stress
        {session : id ของรอบ}
        {member : id ของลูกทีม}
        {trainer : id ของเทรนเนอร์}
        {--at= : เวลา unix (ทศนิยมได้) ที่จะเริ่มยิงพร้อมกัน}
        {--no-waitlist : ไม่ต้องต่อคิวสำรองเมื่อเต็ม}';

    protected $description = 'ทดสอบการจองแข่งกันแบบขนาน';

    public function handle(BookingService $bookings): int
    {
        $session = WorkoutSession::findOrFail($this->argument('session'));
        $member = Member::findOrFail($this->argument('member'));
        $trainer = Trainer::findOrFail($this->argument('trainer'));

        // ตั้งทุกอย่างให้พร้อมก่อน แล้วค่อยหมุนรอจนถึงวินาทีที่นัดไว้
        if ($at = $this->option('at')) {
            $target = (float) $at;

            while (microtime(true) < $target) {
                usleep(200);
            }
        }

        try {
            $booking = $bookings->book(
                $session,
                $member,
                $trainer,
                allowWaitlist: ! $this->option('no-waitlist'),
            );

            $this->line(sprintf(
                'OK|member=%d|status=%s|ref=%s',
                $member->id,
                $booking->status->value,
                $booking->reference,
            ));

            return self::SUCCESS;
        } catch (BookingException $e) {
            $this->line(sprintf('REJECT|member=%d|reason=%s|%s', $member->id, $e->reason, $e->getMessage()));

            return self::FAILURE;
        }
    }
}
