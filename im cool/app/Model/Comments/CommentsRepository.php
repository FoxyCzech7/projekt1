<?php

namespace App\Model\Comments;

use App\Model\BaseRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class CommentsRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'comments';
    }

    /**
     * Vrací komentáře pro konkrétní příspěvek, seřazené podle data vytvoření vzestupně
     * 
     * @param int $postId
     * @return Selection
     */
    public function findByPostId(int $postId): Selection
    {
        return $this->getTable()
            ->where('post_id', $postId)
            ->order('created_at ASC');
    }

    /**
     * Najde jeden komentář podle jeho ID
     * 
     * @param int $id
     * @return ActiveRow|null
     */
    public function find(int $id): ?ActiveRow
    {
        return $this->getTable()->get($id);
    }

    /**
     * Vloží nový komentář do databáze
     * 
     * @param array $data
     * @return ActiveRow
     */
    public function insert(array $data): ActiveRow
    {
        return $this->getTable()->insert($data);
    }

    /**
     * Smaže komentář podle ID
     * 
     * @param int $id
     * @return void
     */
    public function delete(int $id): void
    {
        $comment = $this->getTable()->get($id);
        if ($comment) {
            $comment->delete();
        }
    }

    /**
     * Aktualizuje komentář pokud je uživatel vlastníkem
     * 
     * @param int $commentId
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateIfOwner(int $commentId, int $userId, array $data): bool
    {
        $comment = $this->find($commentId);
        if ($comment && $comment->user_id === $userId) {  // opraveno z author_id na user_id
            $comment->update($data);
            return true;
        }
        return false;
    }

    /**
     * Vrací komentáře s uživatelskými jmény pomocí JOIN (přes SQL dotaz)
     * 
     * @param int $postId
     * @return array
     */
    public function findByPostIdWithUsers(int $postId): array
    {
        $sql = 'SELECT comments.*, users.username AS user_name
                FROM comments
                JOIN users ON users.id = comments.user_id
                WHERE comments.post_id = ?
                ORDER BY comments.created_at ASC';

        return $this->database->query($sql, $postId)->fetchAll();
    }
}
