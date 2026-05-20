<?php

namespace App\Model\Posts;

use App\Model\BaseRepository;
use Nette\Database\Table\Selection;
use Nette\Database\Table\ActiveRow;

// Repository pro tabulku 'posts' — zapouzdřuje všechny DB dotazy týkající se příspěvků.
// Logika jako notifikace nebo revize patří do PostFacade, ne sem.
class PostsRepository extends BaseRepository
{
    // Vrátí název tabulky — vyžadováno abstraktní třídou BaseRepository.
    protected function getTableName(): string
    {
        return 'posts';
    }

    // Vrátí všechny veřejné příspěvky (status=published, created_at v minulosti) seřazené od nejnovějšího.
    // Podmínka created_at < NOW() zajišťuje, že plánované příspěvky se ještě nezobrazí.
    public function getPublicArticles(): Selection
    {
        return $this->findAll()
            ->select('id, title, content, created_at, user_id, likes_count, image, is_premium, views, status')
            ->where('status', 'published')
            ->where('created_at < ?', new \DateTime())
            ->order('created_at DESC');
    }

    // Vrátí jednu stránku veřejných příspěvků — používá se pro stránkování na homepage.
    public function getPublicArticlesPage(int $offset, int $limit): Selection
    {
        return $this->findAll()
            ->select('id, title, content, created_at, user_id, likes_count, image, is_premium, views, status')
            ->where('status', 'published')
            ->where('created_at < ?', new \DateTime())
            ->order('created_at DESC')
            ->limit($limit, $offset);
    }

    // Vrátí celkový počet publikovaných příspěvků pro výpočet stránek v Paginator.
    public function getPublicArticlesCount(): int
    {
        return $this->findAll()
            ->where('status', 'published')
            ->where('created_at < ?', new \DateTime())
            ->count('*');
    }

    // Vrátí koncepty (status='draft') daného uživatele seřazené od nejnovějšího.
    public function getUserDrafts(int $userId): Selection
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->order('created_at DESC');
    }

    // Vrátí plánované příspěvky — mají status 'published', ale created_at je v budoucnosti.
    // Metoda fetchAll() vrátí pole, ne Selection, protože caller potřebuje iterovat dvakrát.
    public function getUserScheduled(int $userId): array
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('status', 'published')
            ->where('created_at > ?', new \DateTime())
            ->order('created_at ASC')
            ->fetchAll();
    }

    // Najde příspěvek podle primárního klíče.
    public function findById(int $id): ?ActiveRow
    {
        return $this->getTable()->get($id);
    }

    // Přepíše findAll() z BaseRepository — přidá výchozí řazení od nejnovějšího.
    public function findAll(): Selection
    {
        return $this->getTable()->order('created_at DESC');
    }

    // Vytvoří nový příspěvek; pokud je zadán $scheduledAt, použije se jako datum zveřejnění.
    public function createPost(
        string $title, string $content, int $userId,
        ?string $image = null, bool $isPremium = false,
        string $status = 'published', ?\DateTimeInterface $scheduledAt = null,
    ): ActiveRow {
        // Pokud není zadán čas zveřejnění, použijeme aktuální čas.
        $createdAt = $scheduledAt ?? new \DateTime();
        return $this->getTable()->insert([
            'title'        => $title,
            'content'      => $content,
            'user_id'      => $userId,
            'created_at'   => $createdAt,
            'likes_count'  => 0,
            'views'        => 0,
            'image'        => $image,
            'is_premium'   => $isPremium ? 1 : 0,
            'status'       => $status,
        ]);
    }

    // Aktualizuje příspěvek; null parametry se ignorují — aktualizují se jen předané hodnoty.
    public function updatePost(
        int $id, string $title, string $content,
        ?string $image = null, ?bool $isPremium = null,
        ?string $status = null, ?\DateTimeInterface $scheduledAt = null,
    ): void {
        $data = ['title' => $title, 'content' => $content];
        // Volitelné pole se přidají jen pokud jsou explicitně předány (ne null).
        if ($image !== null)    { $data['image']      = $image; }
        if ($isPremium !== null){ $data['is_premium']  = $isPremium ? 1 : 0; }
        if ($status !== null)   { $data['status']      = $status; }
        if ($scheduledAt !== null) { $data['created_at'] = $scheduledAt; }
        $this->getTable()->where('id', $id)->update($data);
    }

    // Najde příspěvek podle přesného názvu — pro detekci duplikátů při RSS importu.
    public function findByTitle(string $title): ?ActiveRow
    {
        return $this->getTable()->where('title', $title)->fetch();
    }

    // Zvýší počitadlo zobrazení o 1 pomocí SQL += (atomická operace, bez race condition).
    public function incrementViews(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['views+=' => 1]);
    }

    // Zvýší počitadlo lajků o 1 — voláno z PostFacade::toggleLike().
    public function incrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count+=' => 1]);
    }

    // Sníží počitadlo lajků o 1 — voláno z PostFacade::toggleLike() při odebrání lajku.
    public function decrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count-=' => 1]);
    }
}
