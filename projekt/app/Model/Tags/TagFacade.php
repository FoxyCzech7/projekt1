<?php

namespace App\Model\Tags;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

// Fasada pro spravu tagu a jejich vazeb na prispevky.
// Pracuje primo s Explorer (bez repository vrstvy), protoze operace zahrnuji
// cross-table JOIN dotazy a neni treba izolace specificka pro repository.
final class TagFacade
{
    public function __construct(private Explorer $database) {}

    // Vrati vsechny tagy serazene abecedne - pro zobrazeni v navigaci nebo vyberu.
    public function getAllTags(): array
    {
        return $this->database->table('tags')->order('name ASC')->fetchAll();
    }

    // Najde tag podle URL slug - pouziva se pri filtrovani prispevku podle tagu.
    public function findBySlug(string $slug): ?ActiveRow
    {
        return $this->database->table('tags')->where('slug', $slug)->fetch();
    }

    // Vrati tagy prirazene k danemu prispevku pres JOIN tabulku post_tags.
    public function getPostTags(int $postId): array
    {
        return $this->database->query(
            'SELECT tags.* FROM tags
             JOIN post_tags ON post_tags.tag_id = tags.id
             WHERE post_tags.post_id = ?
             ORDER BY tags.name',
            $postId
        )->fetchAll();
    }

    // Synchronizuje tagy prispevku z carkami oddelenych textu (napr. "tanky, drony, letectvo").
    // Nejprve smaze vsechny stavajici vazby, pak vytvori nove - jednoducha ale atomicka operace.
    // Tagy, ktere jeste neexistuji, se automaticky vytvoří s vygenerovanym slugem.
    public function syncPostTags(int $postId, string $tagString): void
    {
        $this->database->table('post_tags')->where('post_id', $postId)->delete();

        $names = array_values(array_filter(array_map('trim', explode(',', $tagString))));
        if (!$names) {
            return;
        }

        $slugs = array_map([$this, 'slugify'], $names);

        // jeden dotaz pro vsechny existujici tagy najednou
        $existing = $this->database->table('tags')
            ->where('slug', $slugs)
            ->fetchPairs('slug', 'id');

        $tagIds = [];
        foreach ($names as $i => $name) {
            $slug = $slugs[$i];
            if (isset($existing[$slug])) {
                $tagIds[] = $existing[$slug];
            } else {
                $newTag   = $this->database->table('tags')->insert(['name' => $name, 'slug' => $slug]);
                $tagIds[] = $newTag->id;
            }
        }

        // batch INSERT vsech vazeb najednou
        $this->database->table('post_tags')->insert(
            array_map(fn($tagId) => ['post_id' => $postId, 'tag_id' => $tagId], $tagIds)
        );
    }

    // Vrati tagy prispevku jako carkami oddeleny retezec - pro predvyplneni formulare.
    public function getPostTagString(int $postId): string
    {
        $tags = $this->getPostTags($postId);
        return implode(', ', array_map(fn($t) => $t->name, $tags));
    }

    // Vrati vsechny publikovane prispevky s danym tagem pres SQL JOIN - pro stranku tagu.
    public function getPostsByTag(string $slug): array
    {
        return $this->database->query(
            "SELECT posts.id, posts.title, posts.content, posts.created_at,
                    posts.likes_count, posts.views, posts.is_premium, posts.image
             FROM posts
             JOIN post_tags ON post_tags.post_id = posts.id
             JOIN tags      ON tags.id = post_tags.tag_id
             WHERE tags.slug = ?
               AND posts.status = 'published'
               AND posts.created_at <= NOW()
             ORDER BY posts.created_at DESC",
            $slug
        )->fetchAll();
    }

    // Prevede text na URL-safe slug: odstrani diakritiku, mala pismena, pomlcky misto mezer.
    // iconv TRANSLIT transliteruje znaky (c→c, r→r), IGNORE preskoci neprelozitelne.
    private function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text));
        // trim('-') odstrani prebytecne pomlcky na zacatku a konci
        return trim($text, '-');
    }
}
