<?php

namespace App\Model\Posts;

use App\Model\BaseRepository;

// Repository pro tabulku 'post_revisions' — ukládá historii změn příspěvků.
// Revize se vytváří vždy před přepsáním příspěvku, aby šlo obsah obnovit.
class RevisionRepository extends BaseRepository
{
    // Vrátí název tabulky — vyžadováno abstraktní třídou BaseRepository.
    protected function getTableName(): string
    {
        return 'post_revisions';
    }

    // Uloží snímek příspěvku před jeho úpravou — zachová původní titulek, obsah a editora.
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

    // Vrátí všechny revize daného příspěvku seřazené od nejnovější — pro zobrazení historie.
    public function getByPost(int $postId): array
    {
        return $this->getTable()
            ->where('post_id', $postId)
            ->order('edited_at DESC')
            ->fetchAll();
    }
}
