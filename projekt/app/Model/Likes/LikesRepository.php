<?php

namespace App\Model\Likes;

use App\Model\BaseRepository;
use Nette\Database\Table\ActiveRow;

// Repository pro tabulku 'likes' - kazdy zaznam reprezentuje jeden lajk uzivatele na prispevku
class LikesRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'likes';
    }

    // Najde lajk daneho uzivatele na danem prispevku, nebo vrati null pokud neexistuje
    // PostFacade::toggleLike() tuto metodu pouziva ke zjisteni, zda lajk pridat nebo odebrat
    public function findByUserAndPost(int $userId, int $postId): ?ActiveRow
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('post_id', $postId)
            ->fetch();
    }

    // Vrati mnozinu ID prispevku, ktere dany uzivatel lajknul (omezeno na predane $postIds)
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
