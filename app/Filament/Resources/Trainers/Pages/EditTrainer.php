<?php

namespace App\Filament\Resources\Trainers\Pages;

use App\Filament\Resources\Trainers\Schemas\TrainerForm;
use App\Filament\Resources\Trainers\TrainerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTrainer extends EditRecord
{
    protected static string $resource = TrainerResource::class;

    /** โหมดขนาดทีมเป็นฟิลด์ช่วยกรอก ต้องแปลงเป็นค่าจริงก่อนบันทึก */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // บัญชีรับเงินบันทึกผ่าน updateBankAccount เพื่อให้ลงประวัติและแจ้งเทรนเนอร์ ไม่ใช่ update ตรง ๆ
        $bank = \Illuminate\Support\Arr::only($data, ['bank_code', 'bank_account_number', 'bank_account_name', 'promptpay_id']);
        $data = \Illuminate\Support\Arr::except($data, array_keys($bank));

        if (filled($bank['bank_code'] ?? null) || filled($bank['bank_account_number'] ?? null) || filled($bank['promptpay_id'] ?? null)) {
            validator($bank, \App\Models\Trainer::bankRules($bank['bank_code'] ?? null))->validate();
            $this->record->updateBankAccount($bank, auth()->user());
        } elseif ($this->record->canReceivePayout()) {
            // ล้างทุกช่อง = ถอดบัญชีออก
            $this->record->updateBankAccount(['bank_code' => null, 'bank_account_number' => null, 'bank_account_name' => null, 'promptpay_id' => null], auth()->user());
        }

        return TrainerForm::resolveTeamSize($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
