<?php

namespace App\Model\Notifications;

use Nette\Database\Explorer;

// Fasáda pro správu notifikací uživatelů.
// Notifikace se vytváří z různých míst (PostFacade, CommentFacade) —
// tato třída je jediné centrální místo pro zápis i čtení notifikací.
final class NotificationFacade
{
    public function __construct(private Explorer $database) {}

    // Vytvoří novou notifikaci pro daného uživatele s typem, zprávou a volitelným odkazem.
    // Typy notifikací: 'post_like', 'comment_like', 'comment_reply' (rozlišuje se v šabloně).
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

    // Vrátí počet nepřečtených notifikací daného uživatele — zobrazuje se jako odznak v navigaci.
    public function getUnreadCount(int $userId): int
    {
        return $this->database->table('notifications')
            ->where('user_id', $userId)
            ->where('read_at IS NULL')
            ->count('*');
    }

    // Vrátí posledních 50 notifikací uživatele seřazených od nejnovější.
    // Limit 50 zabraňuje načítání stovek starých záznamů.
    public function getAll(int $userId): array
    {
        return $this->database->table('notifications')
            ->where('user_id', $userId)
            ->order('created_at DESC')
            ->limit(50)
            ->fetchAll();
    }

    // Označí jednu konkrétní notifikaci jako přečtenou nastavením read_at.
    public function markRead(int $id): void
    {
        $this->database->table('notifications')
            ->where('id', $id)
            ->update(['read_at' => new \DateTimeImmutable()]);
    }

    // Označí všechny nepřečtené notifikace daného uživatele jako přečtené najednou.
    // Volá se automaticky při zobrazení stránky notifikací.
    public function markAllRead(int $userId): void
    {
        $this->database->table('notifications')
            ->where('user_id', $userId)
            ->where('read_at IS NULL')
            ->update(['read_at' => new \DateTimeImmutable()]);
    }
}
