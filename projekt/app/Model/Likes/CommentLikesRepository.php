<?php

namespace App\Model\Likes;

use App\Model\BaseRepository;
use Nette\Database\Table\ActiveRow;

// Repository pro tabulku 'comment_likes' - kazdy zaznam je lajk uzivatele na komentari.
// Analogie k LikesRepository, ale pro komentare misto prispevku.
class CommentLikesRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'comment_likes';
    }

    // Najde lajk daneho uzivatele na danem komentari, nebo vrati null pokud neexistuje.
    // CommentFacade::toggleLike() tuto metodu pouziva ke zjisteni, zda lajk pridat nebo odebrat.
    public function findByUserAndComment(int $userId, int $commentId): ?ActiveRow
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('comment_id', $commentId)
            ->fetch();
    }

    // Vrati mnozinu ID komentaru, ktere dany uzivatel lajknul.
    // fetchPairs('comment_id', 'comment_id') vytvori pole [commentId => commentId] pro O(1) lookup.
    // Na rozdil od postu se nefiltruje podle prispevku - detail prispevku zobrazuje jen jeden post,
    // takze pocet komentaru je vzdy maly a dotaz zustane efektivni.
    public function getUserLikedCommentIds(int $userId): array
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->fetchPairs('comment_id', 'comment_id');
    }
}
