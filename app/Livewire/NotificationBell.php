<?php

namespace App\Livewire;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * กระดิ่งแจ้งเตือนบนแถบเมนู อ่านจากตาราง notifications ของ Laravel
 * เซิร์ฟเวอร์ไม่มี websocket จึง poll ทุกนาทีพอให้เห็นของใหม่โดยไม่ต้องรีเฟรช
 */
class NotificationBell extends Component
{
    #[Computed]
    public function unread(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /** @return Collection<int, DatabaseNotification> */
    #[Computed]
    public function latest(): Collection
    {
        return auth()->user()->notifications()->latest()->limit(15)->get();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // ลิงก์ในแจ้งเตือนสร้างโดยระบบเอง แต่ยังกันไว้ให้พาไปได้เฉพาะในเว็บนี้
        if ($url && str_starts_with($url, url('/'))) {
            $this->redirect($url, navigate: ! str_contains($url, '/admin'));

            return;
        }

        unset($this->unread, $this->latest);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->unread, $this->latest);
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
