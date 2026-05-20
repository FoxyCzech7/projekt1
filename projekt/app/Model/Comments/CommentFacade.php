<?php

namespace App\Model\Comments;

use App\Model\Likes\CommentLikesRepository;
use App\Model\Notifications\NotificationFacade;
use Nette\Database\Table\ActiveRow;

// Fasáda pro operace s komentáři — orchestruje CommentsRepository, CommentLikesRepository
// a NotificationFacade. Presentery volají tuto třídu, nikoli repository přímo.
final class CommentFacade
{
    public function __construct(
        private CommentsRepository $commentsRepository,
        private CommentLikesRepository $commentLikesRepository,
        private NotificationFacade $notificationFacade,
    ) {}

    // Najde komentář podle ID, nebo vrátí null.
    public function findById(int $id): ?ActiveRow
    {
        return $this->commentsRepository->findById($id);
    }

    // Vrátí komentáře příspěvku jako strom objektů — každý uzel má pole $children s odpověďmi.
    // Strom se sestavuje ve dvou průchodech v PHP (ne rekurzivním SQL), aby kód fungoval
    // i na starších verzích MariaDB bez podpory CTE (Common Table Expressions).
    public function getCommentsByPost(int $postId): array
    {
        $byId = [];
        $roots = [];

        // fetchAll() načte všechna data najednou — Selection lze jinak iterovat jen jednou.
        $rows = $this->commentsRepository->findByPostId($postId)->fetchAll();

        // Průchod 1: vytvoří stdClass uzly indexované podle ID, přiřadí username z relace.
        foreach ($rows as $comment) {
            // ref() načte propojený řádek z tabulky 'users' bez dalšího JOIN dotazu.
            $user = $comment->user_id !== null ? $comment->ref('users', 'user_id') : null;
            $node = (object) [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
                'user_id' => $comment->user_id,
                'post_id' => $comment->post_id,
                'parent_id' => $comment->parent_id ?? null,
                // Preferujeme username registrovaného uživatele; jinak zobrazíme guest jméno.
                'username' => $user?->username ?? $comment->name,
                'email' => $user !== null ? ($user->email ?? '') : $comment->email,
                'likes_count' => $comment->likes_count,
                'children' => [],
            ];
            $byId[$comment->id] = $node;
        }

        // Průchod 2: přiřadí uzly do children svého rodiče; kořenové uzly jdou do $roots.
        foreach ($byId as $node) {
            if ($node->parent_id !== null && isset($byId[$node->parent_id])) {
                $byId[$node->parent_id]->children[] = $node;
            } else {
                // Komentář nemá rodiče (nebo rodič neexistuje) — je kořenem stromu.
                $roots[] = $node;
            }
        }

        return $roots;
    }

    // Vloží nový komentář nebo odpověď; $parentId = null znamená kořenový komentář.
    // $userId = null pro nepřihlášené uživatele — ti zadávají jméno a email ručně.
    public function addComment(
        int $postId,
        ?int $userId,
        string $name,
        string $email,
        string $content,
        ?int $parentId = null,
    ): void {
        // likes_count se nastavuje explicitně na 0, protože DB sloupec nemá DEFAULT hodnotu.
        $this->commentsRepository->insert([
            'post_id'    => $postId,
            'name'       => $name,
            'email'      => $email,
            'content'    => $content,
            'created_at' => new \DateTimeImmutable(),
            'user_id'    => $userId,
            'likes_count'=> 0,
            'parent_id'  => $parentId,
        ]);

        // Notifikace autorovi rodičovského komentáře — jen pokud odpovídá jiný uživatel.
        if ($parentId !== null && $userId !== null) {
            $parent = $this->commentsRepository->findById($parentId);
            if ($parent && $parent->user_id !== null && $parent->user_id !== $userId) {
                $this->notificationFacade->create(
                    $parent->user_id,
                    'comment_reply',
                    $name . ' odpověděl/a na váš komentář.',
                    '/post/show/' . $postId,
                );
            }
        }
    }

    // Smaže komentář podle ID — odpovědi zůstanou v DB (osirují), DB CASCADE je neřeší.
    public function deleteComment(int $id): void
    {
        $this->commentsRepository->delete($id);
    }

    // Aktualizuje obsah komentáře a nastaví updated_at na aktuální čas.
    public function updateComment(int $id, string $content): void
    {
        $comment = $this->commentsRepository->findById($id);
        if ($comment) {
            $comment->update(['content' => $content, 'updated_at' => new \DateTimeImmutable()]);
        }
    }

    // Přidá nebo odebere lajk na komentáři a aktualizuje počítadlo.
    // Zasahuje do dvou tabulek (comment_likes + comments), proto logika patří do fasády.
    public function toggleLike(int $commentId, int $userId): void
    {
        $like = $this->commentLikesRepository->findByUserAndComment($userId, $commentId);
        if ($like) {
            // Lajk existuje — odebereme ho a snížíme počitadlo.
            $like->delete();
            $this->commentsRepository->decrementLikes($commentId);
        } else {
            // Lajk neexistuje — přidáme ho, zvýšíme počitadlo a notifikujeme autora.
            $this->commentLikesRepository->insert(['user_id' => $userId, 'comment_id' => $commentId]);
            $this->commentsRepository->incrementLikes($commentId);

            // Notifikace autorovi komentáře (ne sobě samému)
            $comment = $this->commentsRepository->findById($commentId);
            if ($comment && $comment->user_id !== null && $comment->user_id !== $userId) {
                $this->notificationFacade->create(
                    $comment->user_id,
                    'comment_like',
                    'Někdo lajknul váš komentář.',
                    '/post/show/' . $comment->post_id,
                );
            }
        }
    }

    // Vrátí množinu ID komentářů, které daný uživatel lajknul — pro zobrazení stavu tlačítka v šabloně.
    public function getUserLikedCommentIds(int $userId): array
    {
        return $this->commentLikesRepository->getUserLikedCommentIds($userId);
    }
}
