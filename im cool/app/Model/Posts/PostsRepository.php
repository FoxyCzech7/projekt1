<?php

namespace App\Model\Posts;

use App\Model\BaseRepository;
use Nette\Database\Table\Selection;
use Nette\Database\Table\ActiveRow;

class PostsRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'posts';
    }

    public function getPublicArticles(): Selection
    {
        return $this->findAll()
            ->select('id, title, content, created_at, user_id, likes_count, image') // přidáno image
            ->where('created_at < ?', new \DateTime())
            ->order('created_at DESC');
    }

    public function getPublicArticlesPage(int $offset, int $limit): Selection
    {
        return $this->findAll()
            ->select('id, title, content, created_at, user_id, likes_count, image') // přidáno image
            ->where('created_at < ?', new \DateTime())
            ->order('created_at DESC')
            ->limit($limit, $offset);
    }

    public function getPublicArticlesCount(): int
    {
        return $this->findAll()
            ->where('created_at < ?', new \DateTime())
            ->count('*');
    }

    public function findById(int $id): ?ActiveRow
    {
        return $this->getTable()->get($id);
    }

    public function findAll(): Selection
    {
        return $this->getTable()->order('created_at DESC');
    }

    /**
     * Vytvoří nový příspěvek (volitelně i s obrázkem).
     */
    public function createPost(string $title, string $content, int $userId, ?string $image = null): ActiveRow
    {
        return $this->getTable()->insert([
            'title' => $title,
            'content' => $content,
            'user_id' => $userId,
            'created_at' => new \DateTime(),
            'likes_count' => 0,
            'image' => $image, // přidáno
        ]);
    }

    /**
     * Aktualizuje příspěvek (volitelně i obrázek).
     */
    public function updatePost(int $id, string $title, string $content, ?string $image = null): void
    {
        $data = [
            'title' => $title,
            'content' => $content,
        ];

        if ($image !== null) {
            $data['image'] = $image;
        }

        $this->getTable()->where('id', $id)->update($data);
    }
}
