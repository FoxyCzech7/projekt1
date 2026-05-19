<?php

namespace App\Model\Posts;

use App\Model\BaseRepository;

class RevisionRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'post_revisions';
    }

    public function saveRevision(int $postId, string $title, string $content, int $editedBy): void
    {
        $this->getTable()->insert([
            'post_id'   => $postId,
            'title'     => $title,
            'content'   => $content,
            'edited_by' => $editedBy,
            'edited_at' => new \DateTimeImmutable(),
        ]);
    }

    public function getByPost(int $postId): array
    {
        return $this->getTable()
            ->where('post_id', $postId)
            ->order('edited_at DESC')
            ->fetchAll();
    }
}
