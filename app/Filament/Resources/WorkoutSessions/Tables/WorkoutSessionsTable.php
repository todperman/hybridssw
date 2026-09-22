<?php

namespace App\Filament\Resources\WorkoutSessions\Tables;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Models\Branch;
use App\Models\WorkoutSession;
use App\Services\SessionGenerator;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class WorkoutSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('date')
                    ->label('วันที่')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label('เวลา')
                    ->formatStateUsing(fn (WorkoutSession $r) => $r->timeLabel())
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('booked_count')
                    ->label('ที่นั่ง')
                    ->badge()
                    ->formatStateUsing(fn (WorkoutSession $r) => "{$r->booked_count}/{$r->capacity}")
                    ->color(fn (WorkoutSession $r) => match (true) {
                        $r->isFull() => 'danger',
                        $r->booked_count > 0 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('waitlist_count')
                    ->label('คิวสำรอง')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state ?: '—'),

                TextColumn::make('mode')
                    ->label('รูปแบบ')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('claimedByTrainer.user.name')
                    ->label('เหมาโดย')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge(),

                TextColumn::make('close_reason')
                    ->label('เหตุผล')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch')->label('สาขา')->relationship('branch', 'name'),
                SelectFilter::make('status')->label('สถานะ')->options(SessionStatus::class),
                SelectFilter::make('mode')->label('รูปแบบ')->options(SessionMode::class),

                Filter::make('upcoming')
                    ->label('เฉพาะรอบที่ยังไม่เริ่ม')
                    ->default()
                    ->query(fn (Builder $q) => $q->where('starts_at', '>=', now())),

                Filter::make('has_waitlist')
                    ->label('มีคิวสำรองรออยู่')
                    ->query(fn (Builder $q) => $q->where('waitlist_count', '>', 0)),
            ])
            ->headerActions([
                // ปุ่มสร้างรอบล่วงหน้า สำหรับกรณีเพิ่งแก้ตารางแล้วอยากเห็นผลทันทีไม่ต้องรอ cron
                Action::make('generate')
                    ->label('สร้างรอบล่วงหน้า')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Select::make('branch_id')
                            ->label('สาขา')
                            ->options(Branch::active()->pluck('name', 'id'))
                            ->required(),

                        DatePicker::make('from')->label('ตั้งแต่')->default(now())->required(),
                        DatePicker::make('until')->label('ถึง')->default(now()->addDays(30))->required()->after('from'),
                    ])
                    ->action(function (array $data, SessionGenerator $generator) {
                        $branch = Branch::findOrFail($data['branch_id']);

                        $result = $generator->generateForBranch($branch, \Carbon\Carbon::parse($data['from']), \Carbon\Carbon::parse($data['until']));

                        Notification::make()
                            ->title('สร้างรอบเรียบร้อย')
                            ->body("สร้างใหม่ {$result['created']} รอบ · มีอยู่แล้ว {$result['skipped']} รอบ")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('adjustCapacity')
                    ->label('ปรับที่นั่ง')
                    ->icon('heroicon-o-users')
                    ->visible(fn (WorkoutSession $r) => ! $r->hasStarted())
                    ->schema([
                        TextInput::make('capacity')
                            ->label('จำนวนที่นั่ง (ไม่รวมเทรนเนอร์)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required(),
                    ])
                    ->fillForm(fn (WorkoutSession $r) => ['capacity' => $r->capacity])
                    ->action(function (WorkoutSession $record, array $data) {
                        // ลดที่นั่งต่ำกว่าคนที่จองไว้แล้วไม่ได้ ต้องให้ยกเลิกรายคนก่อน
                        if ($data['capacity'] < $record->booked_count) {
                            Notification::make()
                                ->title('ลดที่นั่งไม่ได้')
                                ->body("มีคนจองอยู่แล้ว {$record->booked_count} คน ต้องยกเลิกบางรายการก่อน")
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['capacity' => $data['capacity']]);

                        Notification::make()->title('ปรับที่นั่งแล้ว')->success()->send();
                    }),

                Action::make('close')
                    ->label('ปิดรับจอง')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn (WorkoutSession $r) => $r->status === SessionStatus::Open && ! $r->hasStarted())
                    ->schema([
                        TextInput::make('close_reason')->label('เหตุผล')->required(),
                    ])
                    ->action(fn (WorkoutSession $record, array $data) => $record->update([
                        'status' => SessionStatus::Closed,
                        'close_reason' => $data['close_reason'],
                    ])),

                Action::make('reopen')
                    ->label('เปิดรับจองอีกครั้ง')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (WorkoutSession $r) => $r->status === SessionStatus::Closed && ! $r->hasStarted())
                    ->action(fn (WorkoutSession $record) => $record->update([
                        'status' => SessionStatus::Open,
                        'close_reason' => null,
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('closeMany')
                        ->label('ปิดรับจองที่เลือก')
                        ->icon('heroicon-o-lock-closed')
                        ->color('warning')
                        ->schema([TextInput::make('close_reason')->label('เหตุผล')->required()])
                        ->action(function (Collection $records, array $data) {
                            $affected = $records
                                ->filter(fn (WorkoutSession $r) => $r->status === SessionStatus::Open && ! $r->hasStarted())
                                ->each(fn (WorkoutSession $r) => $r->update([
                                    'status' => SessionStatus::Closed,
                                    'close_reason' => $data['close_reason'],
                                ]));

                            Notification::make()->title("ปิดรับจอง {$affected->count()} รอบ")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
