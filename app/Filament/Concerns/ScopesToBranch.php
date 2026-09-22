<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * แอดมินเห็นทุกสาขา ส่วนผู้จัดการและเจ้าหน้าที่เห็นเฉพาะสาขาตัวเอง
 * ใส่ trait นี้กับ Resource ที่ตารางมีคอลัมน์ branch_id
 */
trait ScopesToBranch
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->canSeeAllBranches()) {
            return $query;
        }

        // ผู้ใช้ที่ยังไม่ถูกผูกสาขาไม่ควรเห็นข้อมูลของใครเลย
        return $query->where(
            $query->getModel()->getTable().'.branch_id',
            $user->branch_id ?? 0,
        );
    }
}
