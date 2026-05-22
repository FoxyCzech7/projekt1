<?php

namespace App\Model\Comments;

use App\Model\BaseRepository;
use Nette\Database\Table\Selection;

// Repository pro tabulku 'comments' — zapouzdřuje DB dotazy pro komentáře.
// Stromová struktura a notifikace jsou řešeny v CommentFacade, ne zde.
class CommentsRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'comments';
    }

    // Vrátí komentáře pro daný příspěvek seřazené vzestupně podle data — nejstarší první.
    // Výsledek je Selection (lazy); CommentFacade ho dál zpracuje do stromové struktury.
    public function findByPostId(int $postId): Selection
    {
        return $this->getTable()
            ->where('post_id', $postId)
            ->order('created_at ASC');
    }

    // Vrátí komentáře příspěvku i s daty autora v jednom JOIN dotazu — eliminuje N+1 problém ref().
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

    // Aktualizuje komentář, ale pouze pokud je $userId vlastníkem — ochrana před neoprávněnou editací.
    // Vrací true pokud aktualizace proběhla, false pokud uživatel není vlastník nebo komentář neexistuje.
    public function updateIfOwner(int $commentId, int $userId, array $data): bool
    {
        $comment = $this->findById($commentId);
        // Porovnání user_id (opraveno z dřívějšího author_id na aktuální název sloupce)
        if ($comment && $comment->user_id === $userId) {
            $comment->update($data);
            return true;
        }
        return false;
    }

    // Zvýší počitadlo lajků na komentáři o 1 — voláno z CommentFacade::toggleLike().
    public function incrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count+=' => 1]);
    }

    // Sníží počitadlo lajků na komentáři o 1 — voláno z CommentFacade::toggleLike() při odebrání.
    public function decrementLikes(int $id): void
    {
        $this->getTable()->where('id', $id)->update(['likes_count-=' => 1]);
    }
}
