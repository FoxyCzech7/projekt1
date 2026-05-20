<?php

namespace App\Model\Likes;

use App\Model\BaseRepository;
use Nette\Database\Table\ActiveRow;

// Repository pro tabulku 'comment_likes' — každý záznam je lajk uživatele na komentáři.
// Analogie k LikesRepository, ale pro komentáře místo příspěvků.
class CommentLikesRepository extends BaseRepository
{
    // Vrátí název tabulky — vyžadováno abstraktní třídou BaseRepository.
    protected function getTableName(): string
    {
        return 'comment_likes';
    }

    // Najde lajk daného uživatele na daném komentáři, nebo vrátí null pokud neexistuje.
    // CommentFacade::toggleLike() tuto metodu používá ke zjištění, zda lajk přidat nebo odebrat.
    public function findByUserAndComment(int $userId, int $commentId): ?ActiveRow
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('comment_id', $commentId)
            ->fetch();
    }

    // Vrátí množinu ID komentářů, které daný uživatel lajknul.
    // fetchPairs('comment_id', 'comment_id') vytvoří pole [commentId => commentId] pro O(1) lookup.
    // Na rozdíl od postů se nefiltruje podle příspěvku — detail příspěvku zobrazuje jen jeden post,
    // takže počet komentářů je vždy malý a dotaz zůstane efektivní.
    public function getUserLikedCommentIds(int $userId): array
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->fetchPairs('comment_id', 'comment_id');
    }
}
