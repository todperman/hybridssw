<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * กระทบยอดกับ Omise: ถามผลของรายการที่ยังรออยู่
 * Omise ไม่รับประกันว่า webhook จะส่งซ้ำเมื่อพลาด ตัวนี้จึงเป็นตาข่ายรองรับ
 */
class SyncOmisePaymentsCommand extends Command
{
    protected $signature = 'payments:sync-omise {--hours=48 : ย้อนดูรายการที่สร้างภายในกี่ชั่วโมง}';

    protected $description = 'ตรวจผลรายการชำระผ่าน Omise ที่ยังรออยู่ เผื่อ webhook ไม่มา';

    public function handle(PaymentService $payments): int
    {
        $since = now()->subHours((int) $this->option('hours'));

        $pending = Payment::query()
            ->where('provider', Payment::PROVIDER_OMISE)
            ->where('status', PaymentStatus::Pending->value)
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->get();

        $synced = 0;

        foreach ($pending as $payment) {
            // เริ่มตัดบัตรแล้วไม่ได้รหัสรายการกลับมาภายใน 10 นาที แปลว่าคำขอไม่ถึง Omise
            if (! $payment->provider_charge_id) {
                if ($payment->created_at->lt(now()->subMinutes(10))) {
                    $payment->update(['status' => PaymentStatus::Failed, 'failure_message' => 'ไม่ได้รับผลจากระบบชำระเงิน']);
                }

                continue;
            }

            try {
                $payments->syncOmiseCharge($payment->provider_charge_id);
                $synced++;
            } catch (\Throwable $e) {
                Log::warning('กระทบยอด Omise ไม่สำเร็จ', ['charge' => $payment->provider_charge_id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("ตรวจ {$synced} รายการ");

        return self::SUCCESS;
    }
}
