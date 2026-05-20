<?php

namespace App\Model\Admin;

use Nette\Database\Explorer;

// Fasáda pro admin sekci — agreguje profil uživatele a statistiky aktivity.
// Pracuje přímo s Explorer (ne přes repository), protože potřebuje cross-table agregace
// (SUM, COUNT přes více tabulek) a SQL funkce jako DATE_FORMAT, které by
// v repository pattern generovaly zbytečně mnoho dílčích dotazů.
final class UserProfileFacade
{
    public function __construct(
        private Explorer $database,
    ) {}

    // Vrátí kompletní profil uživatele včetně agregovaných statistik.
    // Vrátí null pokud uživatel s daným ID neexistuje.
    public function getUserProfile(int $userId): ?object
    {
        $user = $this->database->table('users')->get($userId);
        if (!$user) {
            return null;
        }

        // COALESCE zajistí 0 místo NULL pokud uživatel nemá žádné komentáře
        // (SUM vrací NULL pro prázdnou sadu řádků).
        $commentLikes = (int) $this->database->query(
            'SELECT COALESCE(SUM(likes_count), 0) FROM comments WHERE user_id = ?',
            $userId
        )->fetchField();

        return (object) [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email ?? '',
            'role' => $user->role,
            // Sloupce first_name, last_name, last_login jsou volitelné —
            // existují jen pokud byla spuštěna příslušná DB migrace.
            'first_name' => isset($user->first_name) ? $user->first_name : null,
            'last_name' => isset($user->last_name) ? $user->last_name : null,
            'last_login' => isset($user->last_login) ? $user->last_login : null,
            'comment_likes' => $commentLikes,
            'post_count' => $this->database->table('posts')->where('user_id', $userId)->count('*'),
            'comment_count' => $this->database->table('comments')->where('user_id', $userId)->count('*'),
        ];
    }

    // Vrátí počty příspěvků po měsících za posledních 12 měsíců pro graf aktivity.
    // Výsledek je pole [měsíc => počet], např. ['2026-05' => 3, '2026-04' => 1].
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

    // Vrátí počty komentářů po měsících za posledních 12 měsíců pro graf aktivity.
    // Struktura výsledku je stejná jako u getPostsPerMonth.
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

    // Vrátí všechny uživatele seřazené abecedně — pro přehledovou tabulku v admin/default.
    public function getAllUsers(): array
    {
        return $this->database->table('users')
            ->order('username ASC')
            ->fetchAll();
    }

    // Smaže uživatele včetně všech jeho dat v transakci — buď se smaže vše, nebo nic.
    // Pořadí mazání respektuje FK závislosti (cizí klíče):
    //   1. comment_likes na komentářích tohoto uživatele (jiní lajknuli jeho komentáře)
    //   2. likes na příspěvcích tohoto uživatele (jiní lajknuli jeho příspěvky)
    //   3. comment_likes které sám udělil
    //   4. likes které sám udělil
    //   5. komentáře uživatele
    //   6. příspěvky uživatele
    //   7. samotný uživatel
    public function deleteUser(int $userId): void
    {
        $this->database->beginTransaction();
        try {
            // Nejprve sesbíráme ID komentářů a příspěvků — potřebujeme je pro mazání cizích lajků.
            $commentIds = $this->database->table('comments')
                ->where('user_id', $userId)
                ->fetchPairs('id', 'id');
            if ($commentIds) {
                // Smažeme lajky, které jiní uživatelé dali na komentáře tohoto uživatele.
                $this->database->table('comment_likes')
                    ->where('comment_id', $commentIds)
                    ->delete();
            }

            $postIds = $this->database->table('posts')
                ->where('user_id', $userId)
                ->fetchPairs('id', 'id');
            if ($postIds) {
                // Smažeme lajky, které jiní uživatelé dali na příspěvky tohoto uživatele.
                $this->database->table('likes')
                    ->where('post_id', $postIds)
                    ->delete();
            }

            // Smažeme lajky, které tento uživatel sám dal ostatním.
            $this->database->table('comment_likes')->where('user_id', $userId)->delete();
            $this->database->table('likes')->where('user_id', $userId)->delete();

            // Smažeme obsah uživatele — komentáře a příspěvky.
            $this->database->table('comments')->where('user_id', $userId)->delete();
            $this->database->table('posts')->where('user_id', $userId)->delete();

            // Nakonec smažeme samotného uživatele.
            $this->database->table('users')->where('id', $userId)->delete();

            $this->database->commit();
        } catch (\Throwable $e) {
            // Pokud cokoli selže, vrátíme DB do původního stavu.
            $this->database->rollBack();
            throw $e;
        }
    }
}
