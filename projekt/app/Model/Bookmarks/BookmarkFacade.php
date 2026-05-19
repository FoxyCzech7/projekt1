<?php

namespace App\Model\Bookmarks;

use Nette\Database\Explorer;

final class BookmarkFacade
{
    public function __construct(private Explorer $database) {}

    /** Přidá nebo odebere záložku. Vrátí true = přidáno, false = odebráno. */
    public function toggle(int $userId, int $postId): bool
    {
        $row = $this->database->table('bookmarks')
            ->where('user_id', $userId)->where('post_id', $postId)->fetch();
        if ($row) {
            $row->delete();
            return false;
        }
        $this->database->table('bookmarks')->insert([
            'user_id'    => $userId,
            'post_id'    => $postId,
            'created_at' => new \DateTimeImmutable(),
        ]);
        return true;
    }

    public function isBookmarked(int $userId, int $postId): bool
    {
        return (bool) $this->database->table('bookmarks')
            ->where('user_id', $userId)->where('post_id', $postId)->fetch();
    }

    /** Vrátí záložky uživatele s daty příspěvku. */
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
