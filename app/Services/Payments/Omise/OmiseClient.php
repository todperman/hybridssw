<?php

namespace App\Services\Payments\Omise;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * เรียก Omise REST API ตรง ไม่ต้องลง SDK
 *
 * งานที่สร้างเงิน (สร้าง charge คืนเงิน) ไม่ลองซ้ำเอง เพราะอาจได้รายการซ้ำ
 * งานอ่านอย่างเดียวลองซ้ำได้เมื่อเชื่อมต่อไม่ได้ชั่วคราว
 */
class OmiseClient
{
    public function isConfigured(): bool
    {
        return filled($this->secretKey()) && filled($this->publicKey());
    }

    public function publicKey(): ?string
    {
        return config('services.omise.public_key');
    }

    /** คีย์ขึ้นต้นด้วย _test_ = โหมดทดสอบ ไม่มีการตัดเงินจริง */
    public function isTestMode(): bool
    {
        return str_contains((string) $this->secretKey(), '_test_');
    }

    public function createCharge(array $params): array
    {
        return $this->send(fn () => $this->secret()->asForm()->post('/charges', $params));
    }

    public function retrieveCharge(string $chargeId): array
    {
        return $this->send(fn () => $this->secret()->retry(2, 300, fn ($e) => $e instanceof ConnectionException)->get('/charges/'.rawurlencode($chargeId)));
    }

    public function refund(string $chargeId, int $amount, array $metadata = []): array
    {
        return $this->send(fn () => $this->secret()->asForm()->post('/charges/'.rawurlencode($chargeId).'/refunds', array_filter([
            'amount' => $amount,
            'metadata' => $metadata ?: null,
        ])));
    }

    public function retrieveRefund(string $chargeId, string $refundId): array
    {
        return $this->send(fn () => $this->secret()->get('/charges/'.rawurlencode($chargeId).'/refunds/'.rawurlencode($refundId)));
    }

    /** บัญชีของ secret key ใช้ตรวจว่าคีย์ถูกต้อง */
    public function account(): array
    {
        return $this->send(fn () => $this->secret()->get('/account'));
    }

    /** ช่องทางที่บัญชีเปิดใช้และยอดต่ำสุด/สูงสุด ใช้ public key ตามที่ Omise กำหนด */
    public function capability(): array
    {
        return $this->send(fn () => $this->base($this->publicKey())->get('/capability'));
    }

    protected function send(\Closure $request): array
    {
        try {
            /** @var Response $response */
            $response = $request();
        } catch (ConnectionException) {
            throw new OmiseException('ติดต่อระบบชำระเงินไม่ได้ชั่วคราว ลองใหม่อีกครั้ง');
        }

        if ($response->failed() || $response->json('object') === 'error') {
            throw new OmiseException(
                $this->friendlyMessage($response->json('code'), $response->json('message')),
                $response->json('code'),
                $response->status(),
            );
        }

        return $response->json() ?? [];
    }

    protected function friendlyMessage(?string $code, ?string $message): string
    {
        return match ($code) {
            'authentication_failure' => 'คีย์ของ Omise ไม่ถูกต้อง แจ้งแอดมินให้ตรวจการตั้งค่า',
            'used_token', 'invalid_card_token' => 'ข้อมูลบัตรหมดอายุแล้ว กรุณากรอกบัตรใหม่อีกครั้ง',
            'invalid_card', 'invalid_number' => 'ข้อมูลบัตรไม่ถูกต้อง',
            'insufficient_fund', 'insufficient_balance' => 'ยอดเงินในบัตรไม่พอ',
            'stolen_or_lost_card' => 'บัตรนี้ใช้ชำระไม่ได้ กรุณาใช้บัตรอื่น',
            'payment_rejected', 'failed_processing' => 'ธนาคารปฏิเสธรายการ กรุณาลองบัตรอื่นหรือติดต่อธนาคาร',
            'amount_below_limit' => 'ยอดชำระต่ำกว่าขั้นต่ำของช่องทางนี้ (20 บาท)',
            'amount_above_limit' => 'ยอดชำระเกินเพดานของช่องทางนี้',
            'source_not_enabled', 'not_found' => 'ช่องทางชำระนี้ยังไม่เปิดใช้ในบัญชี Omise',
            default => $message ? 'ระบบชำระเงินแจ้งว่า: '.$message : 'ระบบชำระเงินไม่รับรายการนี้',
        };
    }

    protected function secretKey(): ?string
    {
        return config('services.omise.secret_key');
    }

    protected function secret(): PendingRequest
    {
        if (! $this->secretKey()) {
            throw new OmiseException('ยังไม่ได้ตั้ง OMISE_SECRET_KEY');
        }

        return $this->base($this->secretKey());
    }

    protected function base(?string $key): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.omise.api_url'), '/'))
            ->withBasicAuth((string) $key, '')
            ->withHeaders(['Omise-Version' => config('services.omise.api_version', '2019-05-29')])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);
    }
}
