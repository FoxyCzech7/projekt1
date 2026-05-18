<?php

namespace App\Model\Likes;

use App\Model\BaseRepository;
use Nette\Database\Table\ActiveRow;

/**
 * Repository pro tabulku comment_likes (lajky na komentářích).
 * Každý záznam je dvojice user_id + comment_id.
 */
class CommentLikesRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'comment_likes';
    }

    public function findByUserAndComment(int $userId, int $commentId): ?ActiveRow
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('comment_id', $commentId)
            ->fetch();
    }

    /**
     * Vrátí množinu ID komentářů, které daný uživatel lajknul.
     *
     * fetchPairs('comment_id', 'comment_id') vytvoří pole [commentId => commentId]
     * pro O(1) lookup v šabloně: isset($userHasLikedComments[$comment->id]).
     *
     * Na rozdíl od postů tady nefiltrujeme podle konkrétního příspěvku,
     * protože stránka zobrazuje komentáře jen jednoho postu — dotaz je vždy malý.
     */
    public function getUserLikedCommentIds(int $userId): array
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->fetchPairs('comment_id', 'comment_id');
    }
}
