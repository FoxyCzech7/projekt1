<?php

namespace App\Model\Bookmarks;

use Nette\Database\Explorer;

// Fasáda pro správu záložek — uživatel si může uložit příspěvky pro pozdější čtení.
// Pracuje přímo s Explorer (bez repository vrstvy), protože operace jsou jednoduché a přímé.
final class BookmarkFacade
{
    public function __construct(private Explorer $database) {}

    // Přidá záložku pokud neexistuje, nebo ji odebere pokud existuje (toggle chování).
    // Vrátí true = záložka byla přidána, false = záložka byla odebrána.
    public function toggle(int $userId, int $postId): bool
    {
        $row = $this->database->table('bookmarks')
            ->where('user_id', $userId)->where('post_id', $postId)->fetch();
        if ($row) {
            // Záložka existuje — odebereme ji.
            $row->delete();
            return false;
        }
        // Záložka neexistuje — vytvoříme ji s aktuálním časem.
        $this->database->table('bookmarks')->insert([
            'user_id'    => $userId,
            'post_id'    => $postId,
            'created_at' => new \DateTimeImmutable(),
        ]);
        return true;
    }

    // Zjistí, zda má daný uživatel příspěvek v záložkách — pro zobrazení stavu tlačítka.
    public function isBookmarked(int $userId, int $postId): bool
    {
        return (bool) $this->database->table('bookmarks')
            ->where('user_id', $userId)->where('post_id', $postId)->fetch();
    }

    // Vrátí záložky uživatele s daty příspěvků přes SQL JOIN, seřazené od nejnovější záložky.
    // bookmarked_at alias odlišuje datum záložky od data vytvoření příspěvku (created_at).
    public function getUserBookmarks(int $userId): array
    {
        return $this->database->query(
            'SELECT posts.id, posts.title, posts.created_at, bookmarks.created_at AS bookmarked_at
             FROM bookmarks
             JOIN posts ON posts.id = bookmarks.post_id
             WHERE bookmarks.user_id = ?
             ORDER BY bookmarks.created_at DESC',
            $userId
        )->fetchAll();
    }
}
