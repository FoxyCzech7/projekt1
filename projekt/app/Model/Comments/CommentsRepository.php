<?php

namespace App\Model\Comments;

use App\Model\BaseRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

// Repository pro tabulku 'comments' — zapouzdřuje DB dotazy pro komentáře.
// Stromová struktura a notifikace jsou řešeny v CommentFacade, ne zde.
class CommentsRepository extends BaseRepository
{
    // Vrátí název tabulky — vyžadováno abstraktní třídou BaseRepository.
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

    // Najde jeden komentář podle ID — alias pro getTable()->get(), zachovává konzistenci API.
    public function find(int $id): ?ActiveRow
    {
        return $this->getTable()->get($id);
    }

    // Vloží nový komentář do databáze a vrátí vytvořený řádek.
    public function insert(array $data): ActiveRow
    {
        return $this->getTable()->insert($data);
    }

    // Smaže komentář podle ID; nic nedělá pokud ID neexistuje.
    public function delete(int $id): void
    {
        $comment = $this->getTable()->get($id);
        if ($comment) {
            $comment->delete();
        }
    }

    // Aktualizuje komentář, ale pouze pokud je $userId vlastníkem — ochrana před neoprávněnou editací.
    // Vrací true pokud aktualizace proběhla, false pokud uživatel není vlastník nebo komentář neexistuje.
    public function updateIfOwner(int $commentId, int $userId, array $data): bool
    {
        $comment = $this->find($commentId);
        // Porovnání user_id (opraveno z dřívějšího author_id na aktuální název sloupce)
        if ($comment && $comment->user_id === $userId) {
            $comment->update($data);
            return true;
        }
        return false;
    }

    // Vrátí komentáře s uživatelskými jmény přes přímý SQL JOIN — alternativa k ref() v Selection.
    // Použitelné pokud potřebujeme všechna data najednou bez lazy loading.
    public function findByPostIdWithUsers(int $postId): array
    {
        $sql = 'SELECT comments.*, users.username AS user_name
                FROM comments
                JOIN users ON users.id = comments.user_id
                WHERE comments.post_id = ?
                ORDER BY comments.created_at ASC';

        return $this->database->query($sql, $postId)->fetchAll();
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
