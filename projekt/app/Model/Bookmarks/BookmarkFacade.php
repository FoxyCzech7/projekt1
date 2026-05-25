<?php

namespace App\Model\Bookmarks;

use Nette\Database\Explorer;

// Fasada pro spravu zalozek - uzivatel si muze ulozit prispevky pro pozdejsi cteni
final class BookmarkFacade
{
    public function __construct(private Explorer $database) {}

    // Prida zalozku pokud neexistuje, nebo ji odebere pokud existuje (toggle chovani)
    public function toggle(int $userId, int $postId): bool
    {
        $row = $this->database->table('bookmarks')
            ->where('user_id', $userId)->where('post_id', $postId)->fetch();
        if ($row) {
            // zalozka existuje - odebereme ji
            $row->delete();
            return false;
        }
        // zalozka neexistuje - vytvorime ji s aktualnim casem
        $this->database->table('bookmarks')->insert([
            'user_id'    => $userId,
            'post_id'    => $postId,
            'created_at' => new \DateTimeImmutable(),
        ]);
        return true;
    }

    // Zjisti, zda ma dany uzivatel prispevek v zalozKach - pro zobrazeni stavu tlacitka
    public function isBookmarked(int $userId, int $postId): bool
    {
        return (bool) $this->database->table('bookmarks')
            ->where('user_id', $userId)->where('post_id', $postId)->fetch();
    }

    // Vrati zalozky uzivatele s daty prispevku pres SQL JOIN, serazene od nejnovejsi zalozky
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
