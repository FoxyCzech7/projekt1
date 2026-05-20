<?php

namespace App\Model\Likes;

use App\Model\BaseRepository;
use Nette\Database\Table\ActiveRow;

// Repository pro tabulku 'likes' — každý záznam reprezentuje jeden lajk uživatele na příspěvku.
// Tabulka má unikátní kombinaci (user_id, post_id), takže každý uživatel může lajknout post jen jednou.
class LikesRepository extends BaseRepository
{
    // Vrátí název tabulky — vyžadováno abstraktní třídou BaseRepository.
    protected function getTableName(): string
    {
        return 'likes';
    }

    // Najde lajk daného uživatele na daném příspěvku, nebo vrátí null pokud neexistuje.
    // PostFacade::toggleLike() tuto metodu používá k zjištění, zda lajk přidat nebo odebrat.
    public function findByUserAndPost(int $userId, int $postId): ?ActiveRow
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('post_id', $postId)
            ->fetch();
    }

    // Vrátí množinu ID příspěvků, které daný uživatel lajknul (omezeno na předané $postIds).
    // fetchPairs('post_id', 'post_id') vytvoří pole [postId => postId] pro O(1) lookup v šabloně:
    // isset($userHasLiked[$post->id]) je rychlejší než in_array().
    // Guard na prázdné $postIds zabraňuje neplatnému SQL: WHERE post_id IN ().
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
