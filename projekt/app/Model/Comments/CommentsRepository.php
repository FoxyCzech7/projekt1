<?php

namespace App\Model\Comments;

use App\Model\BaseRepository;
use Nette\Database\Table\Selection;

// Repository pro tabulku 'comments' - zapouzdřuje DB dotazy pro komentare.
// Stromova struktura a notifikace jsou reseny v CommentFacade, ne zde.
class CommentsRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'comments';
    }

    // Vrati komentare pro dany prispevek serazene vzestupne podle data - nejstarsi prvni.
    // Vysledek je Selection (lazy); CommentFacade ho dal zpracuje do stromove struktury.
    public function findByPostId(int $postId): Selection
    {
        return $this->getTable()
            ->where('post_id', $postId)
            ->order('created_at ASC');
    }

    // Vrati komentare prispevku i s daty autora v jednom JOIN dotazu - eliminuje N+1 problem ref().
    public function findByPostIdWithUsers(int $postId): array
    {
        return $this->database->query(
            'SELECT comments.*, users.username AS user_username, users.email AS user_email
             FROM comments
             LEFT JOIN users ON users.id = comments.user_id
             WHERE comments.post_id = ?
             ORDER BY comments.created_at ASC',
            $postId
        )->fetchAll();
    }

    // Aktualizuje komentar, ale pouze pokud je $userId vlastnikem - ochrana pred neautorizovanou editaci.
    // Vraci true pokud aktualizace probehla, false pokud uzivatel neni vlastnik nebo komentar neexistuje.
    public function updateIfOwner(int $commentId, int $userId, array $data): bool
    {
        $comment = $this->findById($commentId);
        // porovnani user_id (opraveno z drivejsiho author_id na aktualni nazev sloupce)
        if ($comment && $comment->user_id === $userId) {
            $comment->update($data);
            return true;
        }
        return false;
    }

    // Zvysi pocitadlo lajku na komentari o 1 - volano z CommentFacade::toggleLike().
    public function incrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count+=' => 1]);
    }

    // Snizi pocitadlo lajku na komentari o 1 - volano z CommentFacade::toggleLike() pri odebirani.
    public function decrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count-=' => 1]);
    }
}
