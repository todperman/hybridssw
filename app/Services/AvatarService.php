<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * เก็บรูปโปรไฟล์ไว้ที่ disk public
 *
 * รวมไว้ที่เดียวเพราะมีหลายที่ที่อัปโหลดได้ (หน้าโปรไฟล์ตัวเอง และเทรนเนอร์ตั้งรูปให้ลูกทีม)
 * และทุกที่ต้องลบไฟล์เดิมทิ้งเหมือนกัน ไม่งั้นไฟล์ขยะจะค้างสะสม
 */
class AvatarService
{
    public function store(User $user, UploadedFile $file): string
    {
        $this->deleteFile($user);

        $path = $file->store('avatars', 'public');

        $user->forceFill(['avatar_path' => $path])->save();

        return $path;
    }

    public function remove(User $user): void
    {
        $this->deleteFile($user);

        $user->forceFill(['avatar_path' => null])->save();
    }

    protected function deleteFile(User $user): void
    {
        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }
    }
}
