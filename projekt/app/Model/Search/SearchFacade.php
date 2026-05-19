<?php

namespace App\Model\Search;

use Nette\Database\Explorer;

final class SearchFacade
{
    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Hledá příspěvky podle názvu nebo obsahu.
     * Vrací jen published příspěvky.
     */
    public function search(string $query, int $limit = 30): array
    {
        $like = '%' . $query . '%';
        // DISTINCT kvůli JOIN s tagy — jeden post může mít více tagů a duplicitně by se zobrazoval.
        // Shoda v názvu nebo tagu má prioritu před shodou jen v obsahu.
        return $this->database->query(
            "SELECT DISTINCT posts.id, posts.title, posts.content,
                    posts.created_at, posts.likes_count, posts.views, posts.is_premium
             FROM posts
             LEFT JOIN post_tags ON post_tags.post_id = posts.id
             LEFT JOIN tags      ON tags.id = post_tags.tag_id
             WHERE posts.status = 'published'
               AND posts.created_at <= NOW()
               AND (
                   posts.title   LIKE ?
                OR posts.content LIKE ?
                OR tags.name     LIKE ?
               )
             ORDER BY
               (posts.title LIKE ? OR tags.name LIKE ?) DESC,
               posts.created_at DESC
             LIMIT ?",
            $like, $like, $like, $like, $like, $limit
        )->fetchAll();
    }
}
