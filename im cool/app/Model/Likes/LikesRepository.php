<?php

namespace App\Model\Likes;

use App\Model\BaseRepository;
use Nette\Database\Table\ActiveRow;

/**
 * Repository pro tabulku likes (lajky na příspěvcích).
 * Každý záznam je dvojice user_id + post_id.
 */
class LikesRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'likes';
    }

    public function findByUserAndPost(int $userId, int $postId): ?ActiveRow
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('post_id', $postId)
            ->fetch();
    }

    /**
     * Vrátí množinu ID příspěvků, které daný uživatel lajknul.
     *
     * fetchPairs('post_id', 'post_id') vytvoří pole [postId => postId],
     * takže šablona může dělat isset($userHasLiked[$post->id]) v O(1).
     *
     * Prázdný $postIds guard zabraňuje generování neplatného SQL: WHERE post_id IN ().
     */
    public function getUserLikedPostIds(int $userId, array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('post_id', $postIds)
            ->fetchPairs('post_id', 'post_id');
    }
}
