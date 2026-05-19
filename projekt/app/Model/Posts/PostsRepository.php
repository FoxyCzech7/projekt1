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
            ->select('id, title, content, created_at, user_id, likes_count, image, is_premium, views, status')
            ->where('status', 'published')
            ->where('created_at < ?', new \DateTime())
            ->order('created_at DESC');
    }

    public function getPublicArticlesPage(int $offset, int $limit): Selection
    {
        return $this->findAll()
            ->select('id, title, content, created_at, user_id, likes_count, image, is_premium, views, status')
            ->where('status', 'published')
            ->where('created_at < ?', new \DateTime())
            ->order('created_at DESC')
            ->limit($limit, $offset);
    }

    public function getPublicArticlesCount(): int
    {
        return $this->findAll()
            ->where('status', 'published')
            ->where('created_at < ?', new \DateTime())
            ->count('*');
    }

    public function getUserDrafts(int $userId): Selection
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->order('created_at DESC');
    }

    public function findById(int $id): ?ActiveRow
    {
        return $this->getTable()->get($id);
    }

    public function findAll(): Selection
    {
        return $this->getTable()->order('created_at DESC');
    }

    public function createPost(
        string $title, string $content, int $userId,
        ?string $image = null, bool $isPremium = false,
        string $status = 'published', ?\DateTimeInterface $scheduledAt = null,
    ): ActiveRow {
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

    public function updatePost(
        int $id, string $title, string $content,
        ?string $image = null, ?bool $isPremium = null,
        ?string $status = null, ?\DateTimeInterface $scheduledAt = null,
    ): void {
        $data = ['title' => $title, 'content' => $content];
        if ($image !== null)    { $data['image']      = $image; }
        if ($isPremium !== null){ $data['is_premium']  = $isPremium ? 1 : 0; }
        if ($status !== null)   { $data['status']      = $status; }
        if ($scheduledAt !== null) { $data['created_at'] = $scheduledAt; }
        $this->getTable()->where('id', $id)->update($data);
    }

    public function findByTitle(string $title): ?ActiveRow
    {
        return $this->getTable()->where('title', $title)->fetch();
    }

    public function incrementViews(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['views+=' => 1]);
    }

    public function incrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count+=' => 1]);
    }

    public function decrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count-=' => 1]);
    }
}
