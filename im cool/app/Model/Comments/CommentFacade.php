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
     * Vrátí komentáře příspěvku jako strom — každý uzel má pole $children.
     *
     * Strom se sestavuje ve dvou průchodech v PHP (né rekurzivním SQL),
     * aby kód fungoval i na starších verzích MariaDB bez CTE podpory.
     *
     * Průchod 1: vytvoří stdClass uzly s children = [] a indexuje je podle ID.
     * Průchod 2: přiřadí uzly do children rodiče; kořenové uzly jdou do $roots.
     */
    public function getCommentsByPost(int $postId): array
    {
        $byId = [];
        $roots = [];

        // fetchAll() je nutný před prvním průchodem — Selection lze iterovat jen jednou.
        $rows = $this->commentsRepository->findByPostId($postId)->fetchAll();

        foreach ($rows as $comment) {
            $user = $comment->user_id !== null ? $comment->ref('users', 'user_id') : null;
            $node = (object) [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
                'user_id' => $comment->user_id,
                'post_id' => $comment->post_id,
                'parent_id' => $comment->parent_id ?? null,
                'username' => $user?->username ?? $comment->name,
                'email' => $user !== null ? ($user->email ?? '') : $comment->email,
                'likes_count' => $comment->likes_count,
                'children' => [],
            ];
            $byId[$comment->id] = $node;
        }

        foreach ($byId as $node) {
            if ($node->parent_id !== null && isset($byId[$node->parent_id])) {
                $byId[$node->parent_id]->children[] = $node;
            } else {
                $roots[] = $node;
            }
        }

        return $roots;
    }

    /**
     * Vloží nový komentář nebo odpověď na komentář.
     *
     * $parentId null = kořenový komentář; int = odpověď na existující komentář.
     * likes_count se nastavuje explicitně na 0, protože DB sloupec nemá DEFAULT.
     */
    public function addComment(
        int $postId,
        ?int $userId,
        string $name,
        string $email,
        string $content,
        ?int $parentId = null,
    ): void {
        $this->commentsRepository->insert([
            'post_id' => $postId,
            'name' => $name,
            'email' => $email,
            'content' => $content,
            'created_at' => new \DateTimeImmutable(),
            'user_id' => $userId,
            'likes_count' => 0,
            'parent_id' => $parentId,
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
     * Zasahuje do dvou tabulek — proto je logika tady, ne v repository.
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

    public function getUserLikedCommentIds(int $userId): array
    {
        return $this->commentLikesRepository->getUserLikedCommentIds($userId);
    }
}
