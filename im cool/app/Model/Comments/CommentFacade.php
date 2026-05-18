<?php

namespace App\Model\Comments;

use App\Model\Likes\CommentLikesRepository;
use Nette\Database\Table\ActiveRow;

/**
 * Fasáda pro operace s komentáři.
 *
 * Orchestruje CommentsRepository a CommentLikesRepository.
 * Presentery neví o databázových tabulkách — volají jen tuto třídu.
 */
final class CommentFacade
{
    public function __construct(
        private CommentsRepository $commentsRepository,
        private CommentLikesRepository $commentLikesRepository,
    ) {}

    public function findById(int $id): ?ActiveRow
    {
        return $this->commentsRepository->findById($id);
    }

    /**
     * Vrátí komentáře příspěvku jako pole plain objektů obohacených o data uživatele.
     *
     * Nevrací ActiveRow, protože potřebujeme přidat username a email z tabulky users.
     * ref() provede lazy JOIN přes Nette Database Explorer bez extra SQL dotazu na každý řádek.
     */
    public function getCommentsByPost(int $postId): array
    {
        $result = [];
        foreach ($this->commentsRepository->findByPostId($postId) as $comment) {
            // ref() vrátí null pokud user_id je null (komentář hosta).
            // V tom případě bereme jméno a email přímo ze sloupců comments.name / comments.email.
            $user = $comment->user_id !== null ? $comment->ref('users', 'user_id') : null;
            $result[] = (object) [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
                'user_id' => $comment->user_id,
                'post_id' => $comment->post_id,
                'username' => $user?->username ?? $comment->name,
                'email' => $user !== null ? ($user->email ?? '') : $comment->email,
                'likes_count' => $comment->likes_count,
            ];
        }
        return $result;
    }

    /**
     * Vloží nový komentář.
     *
     * likes_count se nastavuje explicitně na 0, protože DB sloupec nemá DEFAULT hodnotu.
     */
    public function addComment(int $postId, ?int $userId, string $name, string $email, string $content): void
    {
        $this->commentsRepository->insert([
            'post_id' => $postId,
            'name' => $name,
            'email' => $email,
            'content' => $content,
            'created_at' => new \DateTimeImmutable(),
            'user_id' => $userId,
            'likes_count' => 0,
        ]);
    }

    public function deleteComment(int $id): void
    {
        $this->commentsRepository->delete($id);
    }

    public function updateComment(int $id, string $content): void
    {
        $comment = $this->commentsRepository->findById($id);
        if ($comment) {
            $comment->update(['content' => $content, 'updated_at' => new \DateTimeImmutable()]);
        }
    }

    /**
     * Přidá nebo odebere lajk a aktualizuje počítadlo na komentáři.
     *
     * Logika je tady (ne v repository), protože zasahuje do dvou tabulek:
     * comment_likes a počítadla likes_count v tabulce comments.
     */
    public function toggleLike(int $commentId, int $userId): void
    {
        $like = $this->commentLikesRepository->findByUserAndComment($userId, $commentId);
        if ($like) {
            $like->delete();
            $this->commentsRepository->decrementLikes($commentId);
        } else {
            $this->commentLikesRepository->insert(['user_id' => $userId, 'comment_id' => $commentId]);
            $this->commentsRepository->incrementLikes($commentId);
        }
    }

    /**
     * Vrátí ID komentářů, které uživatel lajknul — jako pole [commentId => commentId].
     *
     * Associativní pole umožňuje O(1) lookup v šabloně: isset($userHasLikedComments[$comment->id]).
     */
    public function getUserLikedCommentIds(int $userId): array
    {
        return $this->commentLikesRepository->getUserLikedCommentIds($userId);
    }
}
