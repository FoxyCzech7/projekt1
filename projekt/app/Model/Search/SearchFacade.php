<?php

namespace App\Model\Search;

use Nette\Database\Explorer;

// Fasada pro fulltextove vyhledavani v prispëvcich a tazich.
// Vyhledavani probiha pres primy SQL dotaz, protoze potrebuje DISTINCT a razeni
// podle relevance - to Nette Selection API nepodporuje snadno.
final class SearchFacade
{
    public function __construct(
        private Explorer $database,
    ) {}

    // Hleda publikovane prispevky podle shody v nazvu, obsahu nebo tagu.
    // Vysledky jsou serazeny: shoda v nazvu/tagu ma prednost pred shodou jen v obsahu.
    public function search(string $query, int $limit = 30): array
    {
        $like = '%' . $query . '%';
        // DISTINCT je nutny kvuli JOIN s tagy - jeden prispevek ma vice tagu
        // a bez DISTINCT by se ve vysledcich zobrazoval vicekrat.
        // ORDER BY vyraz s (title LIKE ? OR tags.name LIKE ?) vraci 1 pro shodu, 0 pro ne-shodu;
        // DESC razeni tak puts relevantni vysledky na zacatek.
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
