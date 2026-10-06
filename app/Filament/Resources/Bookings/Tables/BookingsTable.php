<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Services\BookingService;
use App\Support\BookingRules;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('รหัสจอง')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('workoutSession.starts_at')
                    ->label('รอบ')
                    ->formatStateUsing(fn (Booking $r) => $r->workoutSession->starts_at->format('d/m/Y').' '.$r->workoutSession->timeLabel())
                    ->sortable(),

                TextColumn::make('member.user.name')
                    ->label('ลูกทีม')
                    ->description(fn (Booking $r) => $r->member->member_code)
                    ->searchable(),

                TextColumn::make('trainer.user.name')
                    ->label('เทรนเนอร์')
                    ->placeholder('จองเอง')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge(),

                TextColumn::make('approved_at')
                    ->label('อนุมัติเมื่อ')
                    ->dateTime('d/m H:i')
                    ->description(fn (Booking $r) => $r->approvedBy?->name)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('ขอเมื่อ')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->toggleable(),

                TextColumn::make('waitlist_position')
                    ->label('คิวที่')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ซ่อนไว้ก่อน ให้ปุ่มอนุมัติ/ไม่อนุมัติมีที่พอบนจอโน้ตบุ๊ก เปิดดูได้จากปุ่มคอลัมน์
                TextColumn::make('confirm_deadline_at')
                    ->label('ต้องยืนยันก่อน')
                    ->dateTime('d/m H:i')
                    ->placeholder('—')
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('checked_in_at')
                    ->label('เช็คอิน')
                    ->dateTime('d/m H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cancelled_late')
                    ->label('ยกเลิกกระชั้น')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? (BookingRules::creditsRequired() ? 'ใช่ หักเครดิต' : 'ใช่') : '—')
                    ->color(fn ($state) => $state ? 'danger' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('สถานะ')->options(BookingStatus::class),
                SelectFilter::make('trainer')->label('เทรนเนอร์')->relationship('trainer.user', 'name')->searchable(),

                Filter::make('today')
                    ->label('เฉพาะรอบวันนี้')
                    ->query(fn (Builder $query) => $query->whereHas(
                        'workoutSession',
                        fn ($s) => $s->whereDate('date', now()->toDateString())
                    )),

                Filter::make('awaiting_confirmation')
                    ->label('รอยืนยันสิทธิ์จากคิวสำรอง')
                    ->query(fn (Builder $query) => $query->whereNotNull('confirm_deadline_at')
                        ->where('status', BookingStatus::Booked->value)),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('อนุมัติ')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Booking $r) => $r->status === BookingStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('อนุมัติการจองนี้')
                    ->modalDescription(fn (Booking $r) => $r->member->user->name.' · '
                        .$r->workoutSession->starts_at->format('d/m/Y').' '.$r->workoutSession->timeLabel())
                    ->modalSubmitActionLabel('อนุมัติ')
                    ->action(function (Booking $record, BookingService $bookings) {
                        try {
                            $bookings->approve($record, auth()->user());
                            Notification::make()->title('อนุมัติแล้ว')->success()->send();
                        } catch (BookingException $e) {
                            Notification::make()->title('อนุมัติไม่ได้')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('reject')
                    ->label('ไม่อนุมัติ')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Booking $r) => $r->status === BookingStatus::Pending)
                    ->modalHeading('ไม่อนุมัติการจองนี้')
                    ->modalDescription('ที่นั่งที่กันไว้จะถูกปล่อยให้คนอื่นจองได้ทันที')
                    ->modalSubmitActionLabel('ยืนยันไม่อนุมัติ')
                    ->schema([
                        Textarea::make('reason')
                            ->label('เหตุผล (สมาชิกจะเห็นข้อความนี้)')
                            ->default('ไม่พบการชำระเงิน')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (Booking $record, array $data, BookingService $bookings) {
                        try {
                            $bookings->reject($record, auth()->user(), $data['reason']);
                            Notification::make()->title('ปฏิเสธคำขอแล้ว')->body('คืนที่นั่งให้รอบเรียบร้อย')->success()->send();
                        } catch (BookingException $e) {
                            Notification::make()->title('ดำเนินการไม่ได้')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('checkIn')
                    ->label('เช็คอิน')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Booking $r) => $r->status === BookingStatus::Booked)
                    ->action(function (Booking $record, BookingService $bookings) {
                        try {
                            $bookings->checkIn($record, auth()->user());
                            Notification::make()->title('เช็คอินแล้ว')->success()->send();
                        } catch (BookingException $e) {
                            Notification::make()->title('เช็คอินไม่ได้')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('noShow')
                    ->label('ไม่มาตามนัด')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('ที่นั่งจะถูกคืนให้รอบ และสะสมสถิติของลูกทีม ครบตามเกณฑ์จะถูกระงับสิทธิ์อัตโนมัติ')
                    ->visible(fn (Booking $r) => $r->status === BookingStatus::Booked && $r->workoutSession->starts_at->isPast())
                    ->action(function (Booking $record, BookingService $bookings) {
                        try {
                            $bookings->markNoShow($record, auth()->user());
                            Notification::make()->title('บันทึกแล้ว')->success()->send();
                        } catch (BookingException $e) {
                            Notification::make()->title('บันทึกไม่ได้')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('ยกเลิก')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (Booking $r) => $r->status->isCancellable())
                    ->schema([
                        Textarea::make('reason')->label('เหตุผล')->required()->rows(2),
                    ])
                    ->action(function (Booking $record, array $data, BookingService $bookings) {
                        try {
                            $bookings->cancel($record, auth()->user(), $data['reason']);

                            Notification::make()
                                ->title('ยกเลิกแล้ว')
                                ->body(match (true) {
                                    ! BookingRules::creditsRequired() => 'คืนที่นั่งและเลื่อนคิวสำรองให้แล้ว',
                                    $record->fresh()->cancelled_late => 'เลยกำหนดยกเลิกฟรี เครดิตถูกหัก',
                                    default => 'คืนเครดิตและเลื่อนคิวสำรองให้แล้ว',
                                })
                                ->success()
                                ->send();
                        } catch (BookingException $e) {
                            Notification::make()->title('ยกเลิกไม่ได้')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label('อนุมัติที่เลือก')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('อนุมัติเฉพาะรายการที่รออนุมัติอยู่ รายการสถานะอื่นจะถูกข้าม')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, BookingService $bookings) {
                            $done = 0;
                            $failed = [];

                            foreach ($records->where('status', BookingStatus::Pending) as $record) {
                                try {
                                    $bookings->approve($record, auth()->user());
                                    $done++;
                                } catch (BookingException $e) {
                                    $failed[] = $record->reference.': '.$e->getMessage();
                                }
                            }

                            Notification::make()
                                ->title("อนุมัติแล้ว {$done} รายการ")
                                ->body($failed ? implode("\n", $failed) : null)
                                ->{$failed ? 'warning' : 'success'}()
                                ->send();
                        }),
                ]),
            ]);
    }
}
