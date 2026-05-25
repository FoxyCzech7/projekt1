<?php

namespace App\Model\Comments;

use App\Model\Likes\CommentLikesRepository;
use App\Model\Notifications\NotificationFacade;
use Nette\Database\Table\ActiveRow;

// Fasada pro operace s komentari - orchestruje CommentsRepository, CommentLikesRepository
// a NotificationFacade. Presentery volaji tuto tridu, nikoli repository primo.
final class CommentFacade
{
    public function __construct(
        private CommentsRepository $commentsRepository,
        private CommentLikesRepository $commentLikesRepository,
        private NotificationFacade $notificationFacade,
    ) {}

    // Najde komentar podle ID, nebo vrati null.
    public function findById(int $id): ?ActiveRow
    {
        return $this->commentsRepository->findById($id);
    }

    // Vrati komentare prispevku jako strom objektu - kazdy uzel ma pole $children s odpovedi.
    // Strom se sestavuje ve dvou pruchodech v PHP (ne rekurzivnim SQL), aby kod fungoval
    // i na starsich verzich MariaDB bez podpory CTE (Common Table Expressions).
    public function getCommentsByPost(int $postId): array
    {
        $byId = [];
        $roots = [];

        // JOIN dotaz nacte komentare i autory najednou - eliminuje N+1 problem oproti ref() v cyklu
        $rows = $this->commentsRepository->findByPostIdWithUsers($postId);

        // pruchod 1: vytvori stdClass uzly indexovane podle ID
        foreach ($rows as $comment) {
            $node = (object) [
                'id'         => $comment->id,
                'content'    => $comment->content,
                'created_at' => $comment->created_at,
                'user_id'    => $comment->user_id,
                'post_id'    => $comment->post_id,
                'parent_id'  => $comment->parent_id ?? null,
                // user_username pochazi z JOIN; pokud NULL (host), pouzijeme name z formulare
                'username'   => $comment->user_username ?? $comment->name,
                'email'      => $comment->user_id !== null ? ($comment->user_email ?? '') : $comment->email,
                'likes_count'=> $comment->likes_count,
                'children'   => [],
            ];
            $byId[$comment->id] = $node;
        }

        // pruchod 2: priradi uzly do children sveho rodice; kořenove uzly jdou do $roots
        foreach ($byId as $node) {
            if ($node->parent_id !== null && isset($byId[$node->parent_id])) {
                $byId[$node->parent_id]->children[] = $node;
            } else {
                // komentar nema rodice (nebo rodic neexistuje) - je korenem stromu
                $roots[] = $node;
            }
        }

        return $roots;
    }

    // Vlozi novy komentar nebo odpoved; $parentId = null znamena kořenovy komentar.
    // $userId = null pro neprihlasene uzivatele - ti zadavaji jmeno a email rucne.
    public function addComment(
        int $postId,
        ?int $userId,
        string $name,
        string $email,
        string $content,
        ?int $parentId = null,
    ): void {
        // likes_count se nastavuje explicitne na 0, protoze DB sloupec nema DEFAULT hodnotu
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

        // notifikace autorovi rodicovského komentare - jen pokud odpovida jiny uzivatel
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

    // Smaze komentar podle ID - odpovedi zustanou v DB (osirují), DB CASCADE je neresi.
    public function deleteComment(int $id): void
    {
        $this->commentsRepository->delete($id);
    }

    // Aktualizuje obsah komentare a nastavi updated_at na aktualni cas.
    public function updateComment(int $id, string $content): void
    {
        $comment = $this->commentsRepository->findById($id);
        if ($comment) {
            $comment->update(['content' => $content, 'updated_at' => new \DateTimeImmutable()]);
        }
    }

    // Prida nebo odebere lajk na komentari a aktualizuje pocitadlo.
    // Zasahuje do dvou tabulek (comment_likes + comments), proto logika patri do fasady.
    public function toggleLike(int $commentId, int $userId): void
    {
        $like = $this->commentLikesRepository->findByUserAndComment($userId, $commentId);
        if ($like) {
            // lajk existuje - odebereme ho a snizime pocitadlo
            $like->delete();
            $this->commentsRepository->decrementLikes($commentId);
        } else {
            // lajk neexistuje - pridame ho, zvysime pocitadlo a notifikujeme autora
            $this->commentLikesRepository->insert(['user_id' => $userId, 'comment_id' => $commentId]);
            $this->commentsRepository->incrementLikes($commentId);

            // notifikace autorovi komentare (ne sobe samemu)
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

    // Vrati mnozinu ID komentaru, ktere dany uzivatel lajknul - pro zobrazeni stavu tlacitka v sablone.
    public function getUserLikedCommentIds(int $userId): array
    {
        return $this->commentLikesRepository->getUserLikedCommentIds($userId);
    }
}
