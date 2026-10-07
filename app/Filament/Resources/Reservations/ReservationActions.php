<?php

namespace App\Filament\Resources\Reservations;

use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Services\Payments\PaymentService;
use App\Services\Reservations\Availability;
use App\Services\Reservations\ReservationService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

/**
 * ปุ่มจัดการการจองของแอดมิน ใช้ร่วมกันทั้งในตารางและหน้ารายละเอียด
 * ทุกปุ่มเรียก service ตัวเดียวกับฝั่งผู้ใช้ กฎการจองจึงมีที่เดียว
 */
class ReservationActions
{
    /** @return array<int, Action> */
    public static function all(): array
    {
        return [
            static::recordPayment(),
            static::reschedule(),
            static::changeTrainer(),
            static::settleRefund(),
            static::cancel(),
        ];
    }

    public static function recordPayment(): Action
    {
        return Action::make('recordPayment')
            ->label('บันทึกรับชำระ')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Reservation $record) => $record->status === ReservationStatus::PendingPayment
                || ($record->status === ReservationStatus::Expired && ! $record->successfulPayment()))
            ->modalHeading('บันทึกว่าได้รับเงินแล้ว')
            ->modalDescription(fn (Reservation $record) => 'ยอดต้องตรงกับ ฿'.number_format((float) $record->amount, 2)
                .' จาก '.$record->payer?->user?->name.' รายการที่หมดอายุแล้วจะยืนยันได้เฉพาะเมื่อเวลายังว่าง')
            ->modalSubmitActionLabel('บันทึกและยืนยันการจอง')
            ->schema([
                TextInput::make('amount')
                    ->label('ยอดที่ได้รับ (บาท)')
                    ->numeric()
                    ->required()
                    ->default(fn (Reservation $record) => $record->amount),
                Textarea::make('note')
                    ->label('หมายเหตุ เช่น เลขอ้างอิงสลิป')
                    ->rows(2),
            ])
            ->action(function (Reservation $record, array $data) {
                static::attempt(function () use ($record, $data) {
                    $payment = app(PaymentService::class)->recordSuccess(
                        $record, Payment::PROVIDER_MANUAL, (string) $data['amount'], null, auth()->user(), $data['note'] ?? null,
                    );

                    $payment->needs_review
                        ? Notification::make()->title('บันทึกรับเงินแล้ว แต่ยืนยันการจองไม่ได้')->body('เวลานี้ถูกจองไปแล้ว รายการถูกส่งไปที่หน้า "การชำระเงิน" ให้ตรวจและคืนเงิน')->warning()->persistent()->send()
                        : Notification::make()->title('ยืนยันการจองแล้ว')->success()->send();
                });
            });
    }

    public static function reschedule(): Action
    {
        return Action::make('reschedule')
            ->label('เลื่อนวันเวลา')
            ->icon('heroicon-o-calendar-days')
            ->color('gray')
            ->visible(fn (Reservation $record) => $record->status === ReservationStatus::Confirmed && $record->ends_at->isFuture())
            ->modalDescription('ใช้ Trainer ผู้เข้าร่วม และราคาเดิม แอดมินเลื่อนได้แม้เลยเส้นตาย 00.00 น. แล้ว')
            ->schema([
                DatePicker::make('date')
                    ->label('วันใหม่')
                    ->native(false)
                    ->minDate(today())
                    ->required()
                    ->live(),
                Select::make('time')
                    ->label('เวลาเริ่ม')
                    ->required()
                    ->options(fn (Get $get, Reservation $record) => static::rescheduleOptions($record, $get('date')))
                    ->helperText('แสดงเฉพาะเวลาที่ยิม Trainer และผู้เข้าร่วมว่างครบ'),
                Textarea::make('reason')->label('เหตุผล')->required()->rows(2),
            ])
            ->action(function (Reservation $record, array $data) {
                static::attempt(function () use ($record, $data) {
                    app(ReservationService::class)->reschedule(
                        $record,
                        CarbonImmutable::parse($data['date'])->setTimeFromTimeString($data['time']),
                        auth()->user(),
                        $data['reason'],
                        approved: true,
                    );
                    Notification::make()->title('เลื่อนการจองแล้ว')->body('แจ้งผู้เกี่ยวข้องทุกคนแล้ว')->success()->send();
                });
            });
    }

    public static function changeTrainer(): Action
    {
        return Action::make('changeTrainer')
            ->label('เปลี่ยน Trainer')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (Reservation $record) => $record->status->holdsSlots() && $record->ends_at->isFuture())
            ->schema([
                Select::make('trainer_id')
                    ->label('Trainer คนใหม่')
                    ->required()
                    ->options(fn (Reservation $record) => static::trainerOptions($record))
                    ->helperText('แสดงเฉพาะคนที่ว่างครบทุกชั่วโมงของการจองนี้'),
                Textarea::make('reason')->label('เหตุผล')->required()->rows(2),
            ])
            ->action(function (Reservation $record, array $data) {
                static::attempt(function () use ($record, $data) {
                    app(ReservationService::class)->changeTrainer($record, Trainer::findOrFail($data['trainer_id']), auth()->user(), $data['reason']);
                    Notification::make()->title('เปลี่ยน Trainer แล้ว')->success()->send();
                });
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('ยกเลิกและคืนเงิน')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Reservation $record) => $record->status->holdsSlots())
            ->requiresConfirmation()
            ->modalHeading('ยกเลิกการจองนี้')
            ->modalDescription(fn (Reservation $record) => $record->successfulPayment()
                ? 'ปล่อยเวลาคืนทันที และคืนเงินเต็มจำนวน ฿'.number_format((float) $record->amount, 2).' ให้ผู้ชำระเงินเดิม ('.$record->payer?->user?->name.')'
                : 'ยังไม่มีการชำระเงิน ปล่อยเวลาคืนทันทีโดยไม่มีการคืนเงิน')
            ->modalSubmitActionLabel('ยืนยันยกเลิก')
            ->schema([
                Textarea::make('reason')->label('เหตุผล (ผู้เกี่ยวข้องทุกคนจะเห็น)')->required()->rows(2),
            ])
            ->action(function (Reservation $record, array $data) {
                static::attempt(function () use ($record, $data) {
                    $record = app(ReservationService::class)->cancelByAdmin($record, auth()->user(), $data['reason']);
                    Notification::make()
                        ->title('ยกเลิกแล้ว')
                        ->body($record->refund_status ? 'สถานะคืนเงิน: '.$record->refund_status->label() : null)
                        ->success()
                        ->send();
                });
            });
    }

    /** บันทึกผลคืนเงิน ใช้เมื่อโอนคืนเองแล้ว หรือ Omise คืนไม่สำเร็จแล้วจัดการต่อเอง */
    public static function settleRefund(): Action
    {
        return Action::make('settleRefund')
            ->label('บันทึกผลคืนเงิน')
            ->icon('heroicon-o-receipt-refund')
            ->color('warning')
            ->visible(fn (Reservation $record) => in_array($record->refund_status, [RefundStatus::Pending, RefundStatus::Failed], true))
            ->schema([
                Select::make('status')
                    ->label('ผล')
                    ->options([
                        RefundStatus::Succeeded->value => RefundStatus::Succeeded->label(),
                        RefundStatus::Failed->value => RefundStatus::Failed->label(),
                    ])
                    ->default(RefundStatus::Succeeded->value)
                    ->required(),
                Textarea::make('message')->label('หมายเหตุ เช่น เลขอ้างอิงการโอนคืน')->rows(2),
            ])
            ->action(function (Reservation $record, array $data) {
                $refund = $record->refunds()->latest('id')->firstOrFail();

                app(PaymentService::class)->settleRefund($refund, RefundStatus::from($data['status']), auth()->user(), $data['message'] ?? null);
                Notification::make()->title('บันทึกผลคืนเงินแล้ว')->success()->send();
            });
    }

    /** @return array<string, string> */
    public static function rescheduleOptions(Reservation $record, ?string $date): array
    {
        if (! $date) {
            return [];
        }

        $availability = app(Availability::class);
        $times = $availability->gymStartTimes($record->branch, CarbonImmutable::parse($date), $record->hours, $record->id);

        return collect($times)
            ->filter(fn ($t) => ! $record->trainer || $availability->trainerCovers($record->trainer, $t, $record->hours, $record->id))
            ->filter(fn ($t) => $record->participants->every(fn ($m) => $availability->memberFree($m, $t, $record->hours, $record->id)))
            ->mapWithKeys(fn ($t) => [$t->format('H:i') => $t->format('H:i').' – '.$t->addHours($record->hours)->format('H:i')])
            ->all();
    }

    /** @return array<int, string> */
    public static function trainerOptions(Reservation $record): array
    {
        return app(Availability::class)
            ->availableTrainers($record->branch, $record->starts_at, $record->hours, $record->id)
            ->reject(fn (Trainer $t) => $t->id === $record->trainer_id)
            ->mapWithKeys(fn (Trainer $t) => [$t->id => $t->user->name])
            ->all();
    }

    protected static function attempt(\Closure $action): void
    {
        try {
            $action();
        } catch (ReservationException $e) {
            Notification::make()->title('ทำรายการไม่สำเร็จ')->body($e->getMessage())->danger()->send();
        }
    }
}
