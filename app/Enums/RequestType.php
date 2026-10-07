<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RequestType: string implements HasLabel
{
    case Reschedule = 'reschedule';
    case TrainerWithdrawal = 'trainer_withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::Reschedule => 'ขอเลื่อนวันเวลา',
            self::TrainerWithdrawal => 'Trainer ขอยกเลิกการรับงาน',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
