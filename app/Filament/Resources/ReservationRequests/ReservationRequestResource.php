<?php

namespace App\Filament\Resources\ReservationRequests;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Exceptions\ReservationException;
use App\Filament\Resources\ReservationRequests\Pages\ListReservationRequests;
use App\Filament\Resources\Reservations\ReservationActions;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\ReservationRequest;
use App\Models\Trainer;
use App\Services\Reservations\ReservationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * คำขอเลื่อนหลังเส้นตาย และคำขอยกเลิกการรับงานของ Trainer
 * อนุมัติแล้วระบบเลื่อนหรือเปลี่ยน Trainer ให้ทันทีด้วยกฎเดียวกับฝั่งผู้ใช้
 */
class ReservationRequestResource extends Resource
{
    protected static ?string $model = ReservationRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|\UnitEnum|null $navigationGroup = 'การจอง';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'คำขอ';

    protected static ?string $modelLabel = 'คำขอ';

    protected static ?string $pluralModelLabel = 'คำขอ';

    /** ตารางนี้ไม่มี branch_id ของตัวเอง จำกัดสาขาผ่านการจองแทน */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->canSeeAllBranches()) {
            return $query;
        }

        return $query->whereHas('reservation', fn ($q) => $q->where('branch_id', $user->branch_id ?? 0));
    }

    public static function pendingCount(): int
    {
        return static::getEloquentQuery()->where('status', RequestStatus::Pending->value)->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['reservation.trainer.user', 'reservation.branch', 'requestedBy']))
            ->columns([
                TextColumn::make('type')->label('ประเภท')->badge()->color(fn (RequestType $state) => $state === RequestType::TrainerWithdrawal ? 'danger' : 'info'),

                TextColumn::make('reservation.reference')
                    ->label('การจอง')
                    ->description(fn (ReservationRequest $record) => $record->reservation->dateLabel().' '.$record->reservation->timeLabel())
                    ->url(fn (ReservationRequest $record) => ReservationResource::getUrl('view', ['record' => $record->reservation]))
                    ->searchable(),

                TextColumn::make('new_starts_at')
                    ->label('ขอเลื่อนเป็น')
                    ->formatStateUsing(fn (ReservationRequest $record) => $record->new_starts_at?->locale('th')->isoFormat('ddd D MMM YYYY')
                        .' '.$record->new_starts_at?->format('H:i').'–'.$record->new_ends_at?->format('H:i'))
                    ->placeholder('—'),

                TextColumn::make('requestedBy.name')
                    ->label('ขอโดย')
                    ->description(fn (ReservationRequest $record) => $record->created_at->format('d/m/Y H:i')),

                TextColumn::make('reason')->label('เหตุผล')->wrap()->limit(80),

                TextColumn::make('status')->label('สถานะ')->badge()
                    ->description(fn (ReservationRequest $record) => $record->review_note),
            ])
            ->filters([
                SelectFilter::make('type')->label('ประเภท')->options(RequestType::class),
                SelectFilter::make('status')->label('สถานะ')->options(RequestStatus::class),
            ])
            ->recordActions([
                static::approveAction(),
                static::rejectAction(),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('อนุมัติ')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (ReservationRequest $record) => $record->status === RequestStatus::Pending)
            ->modalHeading(fn (ReservationRequest $record) => $record->type === RequestType::Reschedule ? 'อนุมัติการเลื่อน' : 'อนุมัติและเลือก Trainer คนใหม่')
            ->modalDescription(fn (ReservationRequest $record) => $record->type === RequestType::Reschedule
                ? 'ระบบจะตรวจอีกครั้งว่ายิม Trainer และผู้เข้าร่วมยังว่างเวลาใหม่ แล้วเลื่อนให้ทันที ราคาเท่าเดิม'
                : 'งานจะย้ายไปที่ Trainer คนใหม่ทันที ถ้าไม่มีใครว่าง ให้ไม่อนุมัติ หรือยกเลิกการจองและคืนเงินจากหน้าการจอง')
            ->modalSubmitActionLabel('อนุมัติ')
            ->schema(fn (ReservationRequest $record) => array_filter([
                $record->type === RequestType::TrainerWithdrawal
                    ? Select::make('trainer_id')
                        ->label('Trainer ที่จะมาแทน')
                        ->required()
                        ->options(ReservationActions::trainerOptions($record->reservation))
                        ->helperText('แสดงเฉพาะคนที่ว่างครบทุกชั่วโมงของงานนี้')
                    : null,
                Textarea::make('note')->label('หมายเหตุถึงผู้ขอ')->rows(2),
            ]))
            ->action(function (ReservationRequest $record, array $data) {
                try {
                    app(ReservationService::class)->approveRequest(
                        $record,
                        auth()->user(),
                        isset($data['trainer_id']) ? Trainer::findOrFail($data['trainer_id']) : null,
                        $data['note'] ?? null,
                    );
                    Notification::make()->title('อนุมัติแล้ว')->body('แจ้งผู้เกี่ยวข้องแล้ว')->success()->send();
                } catch (ReservationException $e) {
                    Notification::make()->title('อนุมัติไม่ได้')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('ไม่อนุมัติ')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (ReservationRequest $record) => $record->status === RequestStatus::Pending)
            ->modalSubmitActionLabel('ยืนยันไม่อนุมัติ')
            ->schema([
                Textarea::make('note')->label('เหตุผล (ผู้ขอจะเห็นข้อความนี้)')->required()->rows(2),
            ])
            ->action(function (ReservationRequest $record, array $data) {
                try {
                    app(ReservationService::class)->rejectRequest($record, auth()->user(), $data['note']);
                    Notification::make()->title('ไม่อนุมัติแล้ว')->success()->send();
                } catch (ReservationException $e) {
                    Notification::make()->title('ทำรายการไม่สำเร็จ')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservationRequests::route('/'),
        ];
    }
}
