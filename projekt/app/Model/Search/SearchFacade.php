<?php

namespace App\Model\Search;

use Nette\Database\Explorer;

// Fasáda pro fulltextové vyhledávání v příspěvcích a tazích.
// Vyhledávání probíhá přes přímý SQL dotaz, protože potřebuje DISTINCT a řazení
// podle relevance — to Nette Selection API nepodporuje snadno.
final class SearchFacade
{
    public function __construct(
        private Explorer $database,
    ) {}

    // Hledá publikované příspěvky podle shody v názvu, obsahu nebo tagu.
    // Výsledky jsou seřazeny: shoda v názvu/tagu má přednost před shodou jen v obsahu.
    public function search(string $query, int $limit = 30): array
    {
        // Zabalíme dotaz do LIKE patternů pro SQL.
        $like = '%' . $query . '%';
        // DISTINCT je nutný kvůli JOIN s tagy — jeden příspěvek má více tagů
        // a bez DISTINCT by se ve výsledcích zobrazoval vícekrát.
        // ORDER BY výraz s (title LIKE ? OR tags.name LIKE ?) vrátí 1 pro shodu, 0 pro ne-shodu;
        // DESC řazení tak puts relevantní výsledky na začátek.
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
