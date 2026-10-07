<?php

namespace App\Filament\Resources\Members\Tables;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Collection;
use App\Enums\MemberStatus;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('ชื่อ')
                    ->weight('bold')
                    ->description(fn ($record) => $record->user?->nickname ?: $record->user?->email)
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'user',
                        fn ($q) => $q->where('name', 'like', "%{$search}%")
                            ->orWhere('nickname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                    ))
                    ->sortable(),

                TextColumn::make('member_code')
                    ->label('รหัสสมาชิก')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('คัดลอกรหัสแล้ว')
                    ->searchable(),

                TextColumn::make('primaryTrainer.user.name')
                    ->label('เทรนเนอร์')
                    ->placeholder('ยังไม่มี')
                    ->searchable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('สถานะ')
                    // ระงับอยู่ต้องเห็นวันปลดล็อกด้วย ไม่งั้นต้องกดเข้าไปดูทีละคน
                    ->description(fn ($record) => $record->suspended_until?->isFuture()
                        ? 'ถึง '.$record->suspended_until->format('d/m/Y')
                        : null)
                    ->badge()
                    ->sortable(),

                IconColumn::make('can_book_without_trainer')
                    ->label('ไม่มี Trainer ได้')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Member $record) => $record->can_book_without_trainer ? 'เข้าใช้ยิมโดยไม่มี Trainer ได้' : null),

                TextColumn::make('date_of_birth')
                    ->label('อายุ')
                    ->formatStateUsing(fn ($state) => $state ? $state->age.' ปี' : null)
                    ->placeholder('ไม่ระบุ')
                    ->sortable()
                    ->toggleable(),

                // ข้อมูลติดต่อฉุกเฉินเป็นข้อมูลอ่อนไหว ซ่อนไว้ก่อน เปิดดูเมื่อจำเป็น
                TextColumn::make('emergency_contact_name')
                    ->label('ผู้ติดต่อฉุกเฉิน')
                    ->description(fn ($record) => $record->emergency_contact_phone ?: null)
                    ->placeholder('ไม่มี')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('สมัครเมื่อ')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('สถานะ')
                    ->options(MemberStatus::class)
                    ->multiple(),

                SelectFilter::make('branch_id')
                    ->label('สาขา')
                    ->relationship('branch', 'name'),

                SelectFilter::make('primary_trainer_id')
                    ->label('เทรนเนอร์')
                    ->relationship('primaryTrainer.user', 'name')
                    ->searchable(),

                Filter::make('no_trainer')
                    ->label('มีสิทธิ์เข้าใช้โดยไม่มี Trainer')
                    ->query(fn ($query) => $query->where('can_book_without_trainer', true)),

                TrashedFilter::make()
                    ->label('ที่ถูกลบ'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('อนุมัติ')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Member $record) => $record->awaitsApproval())
                    ->requiresConfirmation()
                    ->modalHeading('อนุมัติการสมัครนี้')
                    ->modalDescription(fn (Member $record) => $record->user->name.' · '.$record->user->phone.' · '.$record->user->email)
                    ->modalSubmitActionLabel('อนุมัติ')
                    ->action(function (Member $record) {
                        $record->approve(auth()->user());
                        Notification::make()->title('อนุมัติแล้ว')->body($record->user->name.' จองรอบได้แล้ว')->success()->send();
                    }),

                Action::make('reject')
                    ->label('ไม่อนุมัติ')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Member $record) => $record->status === MemberStatus::Pending)
                    ->modalHeading('ไม่อนุมัติการสมัครนี้')
                    ->modalDescription('บัญชีจะยังเข้าสู่ระบบได้ แต่จองไม่ได้ และจะเห็นเหตุผลนี้ที่หน้าสถานะการสมัคร')
                    ->modalSubmitActionLabel('ยืนยันไม่อนุมัติ')
                    ->schema([
                        Textarea::make('note')
                            ->label('เหตุผล (สมาชิกจะเห็นข้อความนี้)')
                            ->default('ข้อมูลไม่ครบหรือยืนยันตัวตนไม่ได้ กรุณาติดต่อเจ้าหน้าที่')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (Member $record, array $data) {
                        $record->reject(auth()->user(), $data['note']);
                        Notification::make()->title('ไม่อนุมัติแล้ว')->success()->send();
                    }),

                Action::make('noTrainerPrivilege')
                    ->label(fn (Member $record) => $record->can_book_without_trainer ? 'ถอนสิทธิ์ไม่มี Trainer' : 'ให้สิทธิ์ไม่มี Trainer')
                    ->icon('heroicon-o-shield-check')
                    ->color(fn (Member $record) => $record->can_book_without_trainer ? 'danger' : 'gray')
                    ->visible(fn (Member $record) => ! $record->awaitsApproval())
                    ->modalHeading(fn (Member $record) => ($record->can_book_without_trainer ? 'ถอนสิทธิ์' : 'ให้สิทธิ์').'เข้าใช้ยิมโดยไม่มี Trainer')
                    ->modalDescription(fn (Member $record) => $record->user->name.' · ระบบเก็บประวัติว่าใครเปลี่ยน เมื่อไร และเหตุผล'
                        .(config('gym.reservation.no_trainer_rule', 'all') === 'all' ? ' · การจองแบบไม่มี Trainer ต้องให้ผู้เข้าร่วมทุกคนมีสิทธิ์นี้' : ''))
                    ->schema([
                        Textarea::make('reason')->label('เหตุผล')->required()->rows(2),
                    ])
                    ->action(function (Member $record, array $data) {
                        $record->setNoTrainerPrivilege(! $record->can_book_without_trainer, auth()->user(), $data['reason']);
                        Notification::make()
                            ->title($record->can_book_without_trainer ? 'ให้สิทธิ์แล้ว' : 'ถอนสิทธิ์แล้ว')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label('อนุมัติที่เลือก')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('อนุมัติเฉพาะคนที่ยังรออนุมัติอยู่ คนอื่นจะถูกข้าม')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $waiting = $records->filter(fn (Member $m) => $m->awaitsApproval());
                            $waiting->each(fn (Member $m) => $m->approve(auth()->user()));

                            Notification::make()->title('อนุมัติแล้ว '.$waiting->count().' คน')->success()->send();
                        }),

                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
