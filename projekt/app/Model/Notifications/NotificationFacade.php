<?php

namespace App\Model\Notifications;

use Nette\Database\Explorer;

final class NotificationFacade
{
    public function __construct(private Explorer $database) {}

    public function create(int $userId, string $type, string $message, ?string $link = null): void
    {
        $this->database->table('notifications')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'message'    => $message,
            'link'       => $link,
            'created_at' => new \DateTimeImmutable(),
        ]);
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->database->table('notifications')
            ->where('user_id', $userId)
            ->where('read_at IS NULL')
            ->count('*');
    }

    public function getAll(int $userId): array
    {
        return $this->database->table('notifications')
            ->where('user_id', $userId)
            ->order('created_at DESC')
            ->limit(50)
            ->fetchAll();
    }

    public function markRead(int $id): void
    {
        $this->database->table('notifications')
            ->where('id', $id)
            ->update(['read_at' => new \DateTimeImmutable()]);
    }

    public function markAllRead(int $userId): void
    {
        $this->database->table('notifications')
            ->where('user_id', $userId)
            ->where('read_at IS NULL')
            ->update(['read_at' => new \DateTimeImmutable()]);
    }
}
