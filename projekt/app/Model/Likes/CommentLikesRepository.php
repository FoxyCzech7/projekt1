<?php

namespace App\Model\Likes;

use App\Model\BaseRepository;
use Nette\Database\Table\ActiveRow;

// Repository pro tabulku 'comment_likes' - kazdy zaznam je lajk uzivatele na komentari
// Analogie k LikesRepository, ale pro komentare misto prispevku
class CommentLikesRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'comment_likes';
    }

    // Najde lajk daneho uzivatele na danem komentari, nebo vrati null pokud neexistuje
    // CommentFacade::toggleLike() tuto metodu pouziva ke zjisteni, zda lajk pridat nebo odebrat
    public function findByUserAndComment(int $userId, int $commentId): ?ActiveRow
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('comment_id', $commentId)
            ->fetch();
    }

    // Vrati mnozinu ID komentaru, ktere dany uzivatel lajknul — pouze pro komentare daneho prispevku
    public function getUserLikedCommentIds(int $userId, int $postId): array
    {
        return $this->database->query(
            'SELECT cl.comment_id FROM comment_likes cl
             JOIN comments c ON c.id = cl.comment_id
             WHERE cl.user_id = ? AND c.post_id = ?',
            $userId, $postId
        )->fetchPairs('comment_id', 'comment_id');
    }
}
