<?php

namespace App\Model\Notifications;

use Nette\Database\Explorer;

// Fasada pro spravu notifikaci uzivatelu
// Notifikace se vytvari z ruznych mist (PostFacade, CommentFacade)
// tato trida je jedine centralni misto pro zapis i cteni notifikaci.
final class NotificationFacade
{
    public function __construct(private Explorer $database) {}

    // Vytvori novou notifikaci pro daneho uzivatele s typem, zpravou a volitelnym odkazem
    // Typy notifikaci: 'post_like', 'comment_like', 'comment_reply' (rozlisuje se v sablone)
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

    // Vrati pocet neprectenych notifikaci daneho uzivatele - zobrazuje se jako odznak v navigaci
    public function getUnreadCount(int $userId): int
    {
        return $this->database->table('notifications')
            ->where('user_id', $userId)
            ->where('read_at IS NULL')
            ->count('*');
    }

    // Vrati poslednich 50 notifikaci uzivatele serazenych od nejnovejsi
    // Limit 50 zaranuje nacitani stovek starych zaznamu.
    public function getAll(int $userId): array
    {
        return $this->database->table('notifications')
            ->where('user_id', $userId)
            ->order('created_at DESC')
            ->limit(50)
            ->fetchAll();
    }

    // Oznaci jednu konkretni notifikaci jako pretenou nastavenim read_at
    public function markRead(int $id): void
    {
        $this->database->table('notifications')
            ->where('id', $id)
            ->update(['read_at' => new \DateTimeImmutable()]);
    }

    // Oznaci vsechny neprecetene notifikace daneho uzivatele jako prectene najednou
    // Vola se automaticky pri zobrazeni stranky notifikaci
    public function markAllRead(int $userId): void
    {
        $this->database->table('notifications')
            ->where('user_id', $userId)
            ->where('read_at IS NULL')
            ->update(['read_at' => new \DateTimeImmutable()]);
    }
}
