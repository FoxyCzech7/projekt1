<?php

namespace App\Model\Admin;

use Nette\Database\Explorer;

final class UserProfileFacade
{
    public function __construct(
        private Explorer $database,
    ) {}

    // Vrati kompletni profil uzivatele vcetne agregovanych statistik.
    // Vrati null pokud uzivatel s danym ID neexistuje.
    // Statistiky jsou agregované jedním SQL dotazem místo tří separátních.
    public function getUserProfile(int $userId): ?array
    {
        $user = $this->database->table('users')->get($userId);
        if (!$user) {
            return null;
        }

        $stats = $this->database->query(
            'SELECT
                (SELECT COALESCE(SUM(likes_count), 0) FROM comments WHERE user_id = ?) AS comment_likes,
                (SELECT COUNT(*) FROM posts    WHERE user_id = ?) AS post_count,
                (SELECT COUNT(*) FROM comments WHERE user_id = ?) AS comment_count',
            $userId, $userId, $userId
        )->fetch();

        return [
            'id'           => $user->id,
            'username'     => $user->username,
            'email'        => $user->email ?? '',
            'role'         => $user->role,
            'first_name'   => $user->first_name ?? null,
            'last_name'    => $user->last_name ?? null,
            'last_login'   => $user->last_login ?? null,
            'comment_likes'=> (int) $stats->comment_likes,
            'post_count'   => (int) $stats->post_count,
            'comment_count'=> (int) $stats->comment_count,
        ];
    }

    // Vrati pocty prispevku po mesicich za poslednich 12 mesicu pro graf aktivity.
    // Vysledek je pole [mesic => pocet], napr. ['2026-05' => 3, '2026-04' => 1].
    public function getPostsPerMonth(int $userId): array
    {
        return $this->database->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
             FROM posts
             WHERE user_id = ?
             GROUP BY month
             ORDER BY month DESC
             LIMIT 12",
            $userId
        )->fetchPairs('month', 'cnt');
    }

    // Vrati pocty komentaru po mesicich za poslednich 12 mesicu pro graf aktivity.
    // Struktura vysledku je stejna jako u getPostsPerMonth.
    public function getCommentsPerMonth(int $userId): array
    {
        return $this->database->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
             FROM comments
             WHERE user_id = ?
             GROUP BY month
             ORDER BY month DESC
             LIMIT 12",
            $userId
        )->fetchPairs('month', 'cnt');
    }

    // Vrati vsechny uzivatele serazene abecedne - pro prehledovou tabulku v admin/default.
    public function getAllUsers(): array
    {
        return $this->database->table('users')
            ->order('username ASC')
            ->fetchAll();
    }

    // Smaze uzivatele vcetne vsech jeho dat (prispevky, komentare, lajky)
    public function deleteUser(int $userId): void
    {
        $this->database->beginTransaction();
        try {
            // sesbíráme ID komentaru a prispevku pro mazani cizich like
            $commentIds = $this->database->table('comments')
                ->where('user_id', $userId)
                ->fetchPairs('id', 'id');
            if ($commentIds) {
                // smaze likes, ktere jini uzivatele dali na komentare tohoto uzivatele
                $this->database->table('comment_likes')
                    ->where('comment_id', $commentIds)
                    ->delete();
            }

            $postIds = $this->database->table('posts')
                ->where('user_id', $userId)
                ->fetchPairs('id', 'id');
            if ($postIds) {
                // smaze likes, ktere jini uzivatele dali na prispevky tohoto uzivatele
                $this->database->table('likes')
                    ->where('post_id', $postIds)
                    ->delete();
            }

            // smaze likes, ktere tento uzivatel sam dal ostatnim
            $this->database->table('comment_likes')->where('user_id', $userId)->delete();
            $this->database->table('likes')->where('user_id', $userId)->delete();

            // smaze obsah uzivatele (komentare a prispevky)
            $this->database->table('comments')->where('user_id', $userId)->delete();
            $this->database->table('posts')->where('user_id', $userId)->delete();

            // nakonec smaze samotneho uzivatele
            $this->database->table('users')->where('id', $userId)->delete();

            $this->database->commit();
        } catch (\Throwable $e) {
            // pokud cokoli selze, vrati se DB do puvodnniho stavu
            $this->database->rollBack();
            throw $e;
        }
    }
}
