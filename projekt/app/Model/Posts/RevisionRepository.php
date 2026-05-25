<?php

namespace App\Model\Posts;

use App\Model\BaseRepository;

// Repository pro tabulku 'post_revisions' - uklada historii zmen prispevku
// Revize se vytvari vzdy pred prepisanim prispevku, aby sel obsah obnovit
class RevisionRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'post_revisions';
    }

    // Ulozi snimek prispevku pred jeho upravou - zachova puvodni titulek, obsah a editora
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

    // Vrati vsechny revize daneho prispevku serazene od nejnovejsi - pro zobrazeni historie
    public function getByPost(int $postId): array
    {
        return $this->getTable()
            ->where('post_id', $postId)
            ->order('edited_at DESC')
            ->fetchAll();
    }
}
