<?php

namespace App\Model\Posts;

use App\Model\BaseRepository;
use Nette\Database\Table\Selection;
use Nette\Database\Table\ActiveRow;

// Repository pro tabulku 'posts' - zapouzdřuje vsechny DB dotazy tykajici se prispevku
// Logika jako notifikace nebo revize patri do PostFacade, ne sem
class PostsRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'posts';
    }

    // Vrati vsechny verejne prispevky (status=published, created_at v minulosti) serazene od nejnovejsiho
    // Podminka created_at < NOW() zajistuje, ze planovane prispevky se jeste nezobrazi
    public function getPublicArticles(): Selection
    {
        return $this->findAll()
            ->select('id, title, content, created_at, user_id, likes_count, image, is_premium, views, status')
            ->where('status', 'published')
            ->where('created_at < ?', new \DateTime())
            ->order('created_at DESC');
    }

    // Vrati jednu stranku verejnych prispevku - pouziva se pro strankovani na homepage
    public function getPublicArticlesPage(int $offset, int $limit): Selection
    {
        return $this->findAll()
            ->select('id, title, content, created_at, user_id, likes_count, image, is_premium, views, status')
            ->where('status', 'published')
            ->where('created_at < ?', new \DateTime())
            ->order('created_at DESC')
            ->limit($limit, $offset);
    }

    // Vrati celkovy pocet publikovanych prispevku pro vypocet stranek v Paginator
    public function getPublicArticlesCount(): int
    {
        return $this->findAll()
            ->where('status', 'published')
            ->where('created_at < ?', new \DateTime())
            ->count('*');
    }

    // Vrati koncepty (status='draft') daneho uzivatele serazene od nejnovejsiho
    public function getUserDrafts(int $userId): Selection
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->order('created_at DESC');
    }

    // Vrati planovane prispevky - maji status 'published', ale created_at je v budoucnosti
    public function getUserScheduled(int $userId): array
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('status', 'published')
            ->where('created_at > ?', new \DateTime())
            ->order('created_at ASC')
            ->fetchAll();
    }

    // Najde prispevek podle primarniho klice
    public function findById(int $id): ?ActiveRow
    {
        return $this->getTable()->get($id);
    }

    // Prepise findAll() z BaseRepository - prida vychozi razeni od nejnovejsiho
    public function findAll(): Selection
    {
        return $this->getTable()->order('created_at DESC');
    }

    // Vytvori novy prispevek; pokud je zadan $scheduledAt, pouzije se jako datum zverejneni
    public function createPost(
        string $title, string $content, int $userId,
        ?string $image = null, bool $isPremium = false,
        string $status = 'published', ?\DateTimeInterface $scheduledAt = null,
    ): ActiveRow {
        // pokud neni zadan cas zverejneni, pouzijeme aktualni cas
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

    // Aktualizuje prispevek; null parametry se ignoruji - aktualizuji se jen predane hodnoty
    public function updatePost(
        int $id, string $title, string $content,
        ?string $image = null, ?bool $isPremium = null,
        ?string $status = null, ?\DateTimeInterface $scheduledAt = null,
    ): void {
        $data = ['title' => $title, 'content' => $content];
        // volitelne pole se pridaji jen pokud jsou explicitne predany (ne null)
        if ($image !== null)       { $data['image']      = $image; }
        if ($isPremium !== null)   { $data['is_premium']  = $isPremium ? 1 : 0; }
        if ($status !== null)      { $data['status']      = $status; }
        if ($scheduledAt !== null) { $data['created_at'] = $scheduledAt; }
        $this->getTable()->where('id', $id)->update($data);
    }

    // Najde prispevek podle presneho nazvu - pro detekci duplikatu pri RSS importu
    public function findByTitle(string $title): ?ActiveRow
    {
        return $this->getTable()->where('title', $title)->fetch();
    }

    // Zvysi pocitadlo zobrazeni o 1 pomoci SQL += (atomicka operace, bez race condition)
    public function incrementViews(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['views+=' => 1]);
    }

    // Zvysi pocitadlo lajku o 1 - volano z PostFacade::toggleLike()
    public function incrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count+=' => 1]);
    }

    // Snizi pocitadlo lajku o 1 - volano z PostFacade::toggleLike() pri odebirani lajku
    public function decrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count-=' => 1]);
    }
}
