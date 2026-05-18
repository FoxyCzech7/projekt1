<?php

namespace App\Model\Admin;

use Nette\Database\Explorer;

/**
 * Agreguje data pro profil uživatele v admin sekci.
 *
 * Pracuje přímo s Explorer (ne přes repository) protože potřebuje
 * cross-table agregace (SUM, COUNT přes více tabulek) a SQL funkce
 * jako DATE_FORMAT — ty by v repository pattern zbytečně generovaly
 * mnoho dílčích dotazů.
 */
final class UserProfileFacade
{
    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Vrátí kompletní profil uživatele včetně agregovaných statistik.
     * Vrátí null pokud uživatel neexistuje.
     */
    public function getUserProfile(int $userId): ?object
    {
        $user = $this->database->table('users')->get($userId);
        if (!$user) {
            return null;
        }

        // COALESCE zajistí 0 místo NULL pokud uživatel nemá žádné komentáře.
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
            // existují jen pokud byla spuštěna DB migrace.
            'first_name' => isset($user->first_name) ? $user->first_name : null,
            'last_name' => isset($user->last_name) ? $user->last_name : null,
            'last_login' => isset($user->last_login) ? $user->last_login : null,
            'comment_likes' => $commentLikes,
            'post_count' => $this->database->table('posts')->where('user_id', $userId)->count('*'),
            'comment_count' => $this->database->table('comments')->where('user_id', $userId)->count('*'),
        ];
    }

    /**
     * Vrátí počty příspěvků po měsících pro posledních 12 měsíců.
     * Výsledek je pole [měsíc => počet], např. ['2026-05' => 3].
     */
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

    /**
     * Vrátí počty komentářů po měsících pro posledních 12 měsíců.
     */
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

    /** Vrátí všechny uživatele seřazené podle jména (pro přehled v admin/default). */
    public function getAllUsers(): array
    {
        return $this->database->table('users')
            ->order('username ASC')
            ->fetchAll();
    }

    /**
     * Smaže uživatele včetně všech jeho dat.
     *
     * Pořadí mazání respektuje FK závislosti:
     *   1. comment_likes na komentářích tohoto uživatele
     *   2. likes na příspěvcích tohoto uživatele
     *   3. comment_likes které sám udělil
     *   4. likes které sám udělil
     *   5. komentáře uživatele
     *   6. příspěvky uživatele
     *   7. samotný uživatel
     *
     * Vše probíhá v transakci — buď se smaže vše, nebo nic.
     */
    public function deleteUser(int $userId): void
    {
        $this->database->beginTransaction();
        try {
            // Lajky na komentářích tohoto uživatele (jiní uživatelé je lajkli)
            $commentIds = $this->database->table('comments')
                ->where('user_id', $userId)
                ->fetchPairs('id', 'id');
            if ($commentIds) {
                $this->database->table('comment_likes')
                    ->where('comment_id', $commentIds)
                    ->delete();
            }

            // Lajky na příspěvcích tohoto uživatele (jiní uživatelé je lajkli)
            $postIds = $this->database->table('posts')
                ->where('user_id', $userId)
                ->fetchPairs('id', 'id');
            if ($postIds) {
                $this->database->table('likes')
                    ->where('post_id', $postIds)
                    ->delete();
            }

            // Lajky které uživatel sám udělil
            $this->database->table('comment_likes')->where('user_id', $userId)->delete();
            $this->database->table('likes')->where('user_id', $userId)->delete();

            // Obsah uživatele
            $this->database->table('comments')->where('user_id', $userId)->delete();
            $this->database->table('posts')->where('user_id', $userId)->delete();

            // Samotný uživatel
            $this->database->table('users')->where('id', $userId)->delete();

            $this->database->commit();
        } catch (\Throwable $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
}
