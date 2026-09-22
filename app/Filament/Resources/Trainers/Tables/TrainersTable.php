<?php

namespace App\Filament\Resources\Trainers\Tables;

use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Models\Trainer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('รหัส')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('user.name')
                    ->label('ชื่อ')
                    ->description(fn (Trainer $r) => $r->user->email)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('ประเภท')
                    ->badge(),

                TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge(),

                TextColumn::make('team_members_count')
                    ->label('ลูกทีม')
                    ->counts('teamMembers')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state, Trainer $r) => "{$state} / {$r->teamLimitLabel()}"),

                TextColumn::make('certification_expires_at')
                    ->label('ใบรับรองหมดอายุ')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    // เตือนล่วงหน้า 30 วัน เพื่อให้ตามเอกสารทันก่อนถูกบล็อก
                    ->color(fn (?\Carbon\CarbonInterface $state) => match (true) {
                        $state === null => 'gray',
                        $state->isPast() => 'danger',
                        $state->lte(now()->addDays(30)) => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('สมัครเมื่อ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('สถานะ')->options(TrainerStatus::class),
                SelectFilter::make('type')->label('ประเภท')->options(TrainerType::class),
                SelectFilter::make('branch')->label('สาขา')->relationship('branch', 'name'),

                Filter::make('certification_expiring')
                    ->label('ใบรับรองใกล้หมดอายุ / หมดแล้ว')
                    ->query(fn (Builder $q) => $q->whereNotNull('certification_expires_at')
                        ->whereDate('certification_expires_at', '<=', now()->addDays(30))),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('อนุมัติ')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('อนุมัติเทรนเนอร์')
                    ->modalDescription('อนุมัติแล้วเทรนเนอร์จะเริ่มจองรอบให้ลูกทีมได้ทันที')
                    ->visible(fn (Trainer $r) => $r->status !== TrainerStatus::Approved)
                    ->action(function (Trainer $record) {
                        // กันอนุมัติเทรนเนอร์ภายนอกที่เอกสารหมดอายุ เพราะจะจองไม่ได้อยู่ดี
                        if ($record->hasCertificationExpired()) {
                            Notification::make()
                                ->title('อนุมัติไม่ได้')
                                ->body('ใบรับรองหมดอายุแล้ว ให้เทรนเนอร์อัปเดตเอกสารก่อน')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => TrainerStatus::Approved,
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('อนุมัติแล้ว')
                            ->body("{$record->user->name} เริ่มจองรอบได้แล้ว")
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('ไม่อนุมัติ')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Trainer $r) => $r->status === TrainerStatus::Pending)
                    ->schema([
                        Textarea::make('review_note')
                            ->label('เหตุผล')
                            ->required()
                            ->rows(3)
                            ->helperText('เหตุผลนี้จะถูกส่งให้เทรนเนอร์ทราบ'),
                    ])
                    ->action(function (Trainer $record, array $data) {
                        $record->update([
                            'status' => TrainerStatus::Rejected,
                            'review_note' => $data['review_note'],
                            'approved_by' => auth()->id(),
                        ]);

                        Notification::make()->title('บันทึกการไม่อนุมัติแล้ว')->success()->send();
                    }),

                Action::make('suspend')
                    ->label('ระงับ')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('ระงับแล้วจะจองรอบใหม่ไม่ได้ แต่การจองเดิมยังอยู่')
                    ->visible(fn (Trainer $r) => $r->status === TrainerStatus::Approved)
                    ->action(fn (Trainer $record) => $record->update(['status' => TrainerStatus::Suspended])),

                Action::make('copyInvite')
                    ->label('ลิงก์ชวนลูกทีม')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->visible(fn (Trainer $r) => $r->isApproved())
                    ->modalHeading('ลิงก์สำหรับให้ลูกทีมสมัครเข้าทีม')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('ปิด')
                    ->infolist(fn (Trainer $record) => [
                        \Filament\Infolists\Components\TextEntry::make('url')
                            ->label('ลิงก์')
                            ->state($record->inviteUrl())
                            ->copyable()
                            ->helperText('ส่งลิงก์นี้ให้ลูกทีม สมัครเสร็จจะเข้าทีมอัตโนมัติ'),
                    ]),

                EditAction::make()->label('แก้ไข'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
