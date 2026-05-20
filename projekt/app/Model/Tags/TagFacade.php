<?php

namespace App\Model\Tags;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

// Fasáda pro správu tagů a jejich vazeb na příspěvky.
// Pracuje přímo s Explorer (bez repository vrstvy), protože operace zahrnují
// cross-table JOIN dotazy a není třeba izolace specifická pro repository.
final class TagFacade
{
    public function __construct(private Explorer $database) {}

    // Vrátí všechny tagy seřazené abecedně — pro zobrazení v navigaci nebo výběru.
    public function getAllTags(): array
    {
        return $this->database->table('tags')->order('name ASC')->fetchAll();
    }

    // Najde tag podle URL slug — používá se při filtrování příspěvků podle tagu.
    public function findBySlug(string $slug): ?ActiveRow
    {
        return $this->database->table('tags')->where('slug', $slug)->fetch();
    }

    // Vrátí tagy přiřazené k danému příspěvku přes JOIN tabulku post_tags.
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

    // Synchronizuje tagy příspěvku z čárkami odděleného textu (např. "tanky, drony, letectvo").
    // Nejprve smaže všechny stávající vazby, pak vytvoří nové — jednoduchá ale atomická operace.
    // Tagy, které ještě neexistují, se automaticky vytvoří s vygenerovaným slugem.
    public function syncPostTags(int $postId, string $tagString): void
    {
        // Smažeme všechny stávající vazby příspěvku na tagy.
        $this->database->table('post_tags')->where('post_id', $postId)->delete();

        // Rozdělíme text na pole názvů a odstraníme prázdné hodnoty.
        $names = array_filter(array_map('trim', explode(',', $tagString)));
        foreach ($names as $name) {
            $slug = $this->slugify($name);
            // Pokud tag se slugem existuje, použijeme ho; jinak vytvoříme nový.
            $tag  = $this->database->table('tags')->where('slug', $slug)->fetch()
                 ?? $this->database->table('tags')->insert(['name' => $name, 'slug' => $slug]);
            $this->database->table('post_tags')->insert([
                'post_id' => $postId,
                'tag_id'  => $tag->id,
            ]);
        }
    }

    // Vrátí tagy příspěvku jako čárkami oddělený řetězec — pro předvyplnění formuláře.
    public function getPostTagString(int $postId): string
    {
        $tags = $this->getPostTags($postId);
        return implode(', ', array_map(fn($t) => $t->name, $tags));
    }

    // Vrátí všechny publikované příspěvky s daným tagem přes SQL JOIN — pro stránku tagu.
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

    // Převede text na URL-safe slug: odstraní diakritiku, malá písmena, pomlčky místo mezer.
    // iconv TRANSLIT transliteruje znaky (č→c, ř→r), IGNORE přeskočí nepřeložitelné.
    private function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text));
        // trim('-') odstraní přebytečné pomlčky na začátku a konci.
        return trim($text, '-');
    }
}
