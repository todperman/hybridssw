<?php

namespace App\Filament\Widgets;

use App\Enums\TrainerStatus;
use App\Models\Trainer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * เทรนเนอร์ที่รออนุมัติ
 *
 * เป็นงานที่ค้างแล้วมีคนรออยู่จริง จึงควรอยู่หน้าแรก
 * ไม่ใช่ให้แอดมินต้องนึกได้เองแล้วเข้าไปหาในหน้าเทรนเนอร์
 * ซ่อนทั้งการ์ดเมื่อไม่มีใครรอ แดชบอร์ดจะได้ไม่รกด้วยตารางว่าง
 */
class PendingTrainers extends TableWidget
{
    protected static ?string $heading = 'รออนุมัติ';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (new static)->baseQuery()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery()->with(['user', 'branch']))
            ->defaultSort('created_at')
            ->paginated(false)
            ->columns([
                TextColumn::make('user.name')
                    ->label('ชื่อ')
                    ->weight('bold')
                    ->description(fn (Trainer $r) => $r->user->email),

                TextColumn::make('type')
                    ->label('ประเภท')
                    ->badge(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->toggleable(),

                TextColumn::make('certification_expires_at')
                    ->label('ใบรับรองหมดอายุ')
                    ->date('d/m/Y')
                    ->placeholder('ไม่มีข้อมูล')
                    ->color(fn (Trainer $r) => $r->hasCertificationExpired() ? 'danger' : null),

                TextColumn::make('created_at')
                    ->label('สมัครเมื่อ')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('ตรวจสอบ')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Trainer $r) => route('filament.admin.resources.trainers.edit', $r)),

                Action::make('approve')
                    ->label('อนุมัติ')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('อนุมัติเทรนเนอร์')
                    ->modalDescription('อนุมัติแล้วเทรนเนอร์จะเริ่มจองรอบให้ลูกทีมได้ทันที')
                    ->action(function (Trainer $record) {
                        // เงื่อนไขเดียวกับในหน้าเทรนเนอร์ ใบรับรองหมดอายุแล้วอนุมัติไปก็จองไม่ได้
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
                            ->body($record->user->name.' เริ่มจองรอบได้ทันที')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected function baseQuery(): Builder
    {
        $query = Trainer::query()->where('status', TrainerStatus::Pending->value);
        $user = auth()->user();

        if (! $user || $user->canSeeAllBranches()) {
            return $query;
        }

        return $query->where('branch_id', $user->branch_id ?? 0);
    }
}
