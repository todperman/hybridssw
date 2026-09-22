<?php

namespace App\Filament\Widgets;

use App\Enums\SessionStatus;
use App\Models\WorkoutSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * รอบของวันนี้เรียงตามเวลา
 *
 * แดชบอร์ดเดิมมีแต่ตัวเลขรวม ซึ่งบอกไม่ได้ว่ารอบไหนแน่นหรือรอบไหนโล่ง
 * ตารางนี้ทำให้เห็นทั้งวันในครั้งเดียวโดยไม่ต้องเข้าไปหน้ารอบ
 */
class TodaySessions extends TableWidget
{
    protected static ?string $heading = 'รอบของวันนี้';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->scoped(
                WorkoutSession::query()
                    ->whereDate('date', today())
                    ->where('status', '!=', SessionStatus::Cancelled->value)
            )->with('branch'))
            ->defaultSort('starts_at')
            ->paginated([10, 25])
            ->emptyStateHeading('วันนี้ยังไม่มีรอบ')
            ->emptyStateDescription('สร้างเทมเพลตตารางไว้ แล้วระบบจะสร้างรอบให้อัตโนมัติ')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('เวลา')
                    ->formatStateUsing(fn (WorkoutSession $r) => $r->timeLabel())
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->toggleable(),

                TextColumn::make('booked_count')
                    ->label('ที่นั่ง')
                    ->formatStateUsing(fn (WorkoutSession $r) => $r->booked_count.' / '.$r->capacity)
                    // สีบอกสถานะความแน่นทันทีโดยไม่ต้องคำนวณในหัว
                    ->color(fn (WorkoutSession $r) => match (true) {
                        $r->booked_count >= $r->capacity => 'danger',
                        $r->capacity > 0 && $r->booked_count / $r->capacity >= 0.8 => 'warning',
                        default => 'success',
                    })
                    ->badge(),

                TextColumn::make('waitlist_count')
                    ->label('คิวสำรอง')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge(),
            ]);
    }

    protected function scoped(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user || $user->canSeeAllBranches()) {
            return $query;
        }

        return $query->where('branch_id', $user->branch_id ?? 0);
    }
}
