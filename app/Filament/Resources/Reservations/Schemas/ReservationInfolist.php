<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\Reservation;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('การจอง')
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('starts_at')->label('วันที่')->formatStateUsing(fn (Reservation $record) => $record->dateLabel()),
                    TextEntry::make('ends_at')->label('เวลา')->formatStateUsing(fn (Reservation $record) => $record->timeLabel().' ('.$record->hours.' ชม.)'),
                    TextEntry::make('status')->label('สถานะ')->badge(),
                    TextEntry::make('refund_status')->label('คืนเงิน')->badge()->placeholder('—'),
                    TextEntry::make('trainer.user.name')->label('Trainer')->placeholder('เข้าใช้โดยไม่มี Trainer'),
                    TextEntry::make('payer.user.name')->label('ผู้ชำระเงิน')->helperText(fn (Reservation $record) => $record->payer?->user?->phone),
                    TextEntry::make('amount')->label('ยอด')->money('THB')
                        ->helperText(fn (Reservation $record) => $record->hours.' ชม. × ฿'.number_format((float) $record->hourly_rate, 2)),
                    TextEntry::make('hold_expires_at')->label('ต้องชำระภายใน')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('createdBy.name')->label('จองโดย')
                        ->helperText(fn (Reservation $record) => $record->created_at->format('d/m/Y H:i')),
                    TextEntry::make('branch.name')->label('สาขา'),
                    TextEntry::make('cancellation_reason')->label('เหตุผลที่ยกเลิก')->visible(fn (Reservation $record) => filled($record->cancellation_reason)),
                ]),

            Section::make('ผู้เข้าร่วม')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('participants')
                        ->hiddenLabel()
                        ->columns(['default' => 1, 'sm' => 3])
                        ->schema([
                            TextEntry::make('user.name')->label('ชื่อ'),
                            TextEntry::make('member_code')->label('รหัสสมาชิก')->badge()->color('gray'),
                            TextEntry::make('user.phone')->label('เบอร์โทร')->placeholder('—'),
                        ]),
                ]),

            Grid::make(['default' => 1, 'lg' => 2])
                ->columnSpanFull()
                ->schema([
                    Section::make('การชำระเงิน')
                        ->schema([
                            RepeatableEntry::make('payments')
                                ->hiddenLabel()
                                ->placeholder('ยังไม่มี')
                                ->columns(2)
                                ->schema([
                                    TextEntry::make('amount')->label('ยอด')->money('THB'),
                                    TextEntry::make('status')->label('สถานะ')->badge()
                                        ->helperText(fn ($record) => $record->needs_review ? 'ต้องตรวจสอบ' : null),
                                    TextEntry::make('provider')->label('ช่องทาง')
                                        ->formatStateUsing(fn (string $state) => $state === 'omise' ? 'Omise' : 'แอดมินบันทึก'),
                                    TextEntry::make('paid_at')->label('เวลา')->dateTime('d/m/Y H:i')->placeholder('—'),
                                    TextEntry::make('note')->label('หมายเหตุ')->placeholder('—')->columnSpanFull(),
                                ]),
                        ]),

                    Section::make('การคืนเงิน')
                        ->schema([
                            RepeatableEntry::make('refunds')
                                ->hiddenLabel()
                                ->placeholder('ไม่มี')
                                ->columns(2)
                                ->schema([
                                    TextEntry::make('amount')->label('ยอด')->money('THB'),
                                    TextEntry::make('status')->label('สถานะ')->badge(),
                                    TextEntry::make('reason')->label('เหตุผล')->columnSpanFull(),
                                    TextEntry::make('failure_message')->label('สาเหตุที่ไม่สำเร็จ')->placeholder('—')->columnSpanFull(),
                                ]),
                        ]),
                ]),

            Section::make('คำขอ')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('requests')
                        ->hiddenLabel()
                        ->placeholder('ไม่มี')
                        ->columns(['default' => 1, 'sm' => 4])
                        ->schema([
                            TextEntry::make('type')->label('ประเภท')->badge()->color('gray'),
                            TextEntry::make('status')->label('สถานะ')->badge(),
                            TextEntry::make('requestedBy.name')->label('ขอโดย')
                                ->helperText(fn ($record) => $record->created_at->format('d/m/Y H:i')),
                            TextEntry::make('new_starts_at')->label('ขอเลื่อนเป็น')->dateTime('d/m/Y H:i')->placeholder('—'),
                            TextEntry::make('reason')->label('เหตุผล')->columnSpanFull(),
                            TextEntry::make('review_note')->label('ผลพิจารณา')->placeholder('—')->columnSpanFull(),
                        ]),
                ]),

            Section::make('ประวัติการเปลี่ยนแปลง')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('auditLogs')
                        ->hiddenLabel()
                        ->placeholder('ยังไม่มี')
                        ->columns(['default' => 1, 'sm' => 3])
                        ->schema([
                            TextEntry::make('action')->label('รายการ')->formatStateUsing(fn ($record) => $record->actionLabel()),
                            TextEntry::make('actor.name')->label('ผู้ทำ')->placeholder('ระบบ')
                                ->helperText(fn ($record) => $record->created_at->format('d/m/Y H:i')),
                            TextEntry::make('reason')->label('เหตุผล')->placeholder('—'),
                            TextEntry::make('id')->label('รายละเอียด')
                                ->formatStateUsing(fn ($record) => $record->changeSummary() ?? '—')
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }
}
