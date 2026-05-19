<?php

namespace App\Model\Profile;

use App\Model\Auth\DuplicateEmailException;
use App\Model\Auth\DuplicateNameException;
use Nette\Database\Explorer;
use Nette\Security\Passwords;

/**
 * Fasáda pro profil přihlášeného uživatele.
 * Statistiky, lajknutý obsah a úprava vlastních údajů.
 */
final class ProfileFacade
{
    public function __construct(
        private Explorer $database,
        private Passwords $passwords,
    ) {}

    /** Agregované statistiky uživatele. */
    public function getStats(int $userId): array
    {
        $commentLikes = (int) $this->database->query(
            'SELECT COALESCE(SUM(likes_count), 0) FROM comments WHERE user_id = ?', $userId
        )->fetchField();

        $postLikes = (int) $this->database->query(
            'SELECT COALESCE(SUM(likes_count), 0) FROM posts WHERE user_id = ?', $userId
        )->fetchField();

        return [
            'post_count'              => $this->database->table('posts')->where('user_id', $userId)->count('*'),
            'comment_count'           => $this->database->table('comments')->where('user_id', $userId)->count('*'),
            'comment_likes_received'  => $commentLikes,
            'post_likes_received'     => $postLikes,
        ];
    }

    /** Příspěvky, které uživatel lajknul (jen id, title, created_at). */
    public function getLikedPosts(int $userId): array
    {
        return $this->database->query(
            'SELECT posts.id, posts.title, posts.created_at
             FROM posts
             JOIN likes ON likes.post_id = posts.id
             WHERE likes.user_id = ?
             ORDER BY posts.created_at DESC',
            $userId
        )->fetchAll();
    }

    /** Komentáře, které uživatel lajknul, s názvem příspěvku. */
    public function getLikedComments(int $userId): array
    {
        return $this->database->query(
            'SELECT comments.id, comments.content, comments.post_id,
                    posts.title AS post_title
             FROM comments
             JOIN comment_likes ON comment_likes.comment_id = comments.id
             JOIN posts          ON posts.id = comments.post_id
             WHERE comment_likes.user_id = ?
             ORDER BY comments.created_at DESC',
            $userId
        )->fetchAll();
    }

    /**
     * Aktualizuje profil uživatele.
     * Heslo se aktualizuje jen pokud je neprázdné.
     *
     * @throws DuplicateNameException
     * @throws DuplicateEmailException
     */
    public function updateProfile(int $userId, string $username, string $email,
        string $firstName, string $lastName, string $password): void
    {
        // Kontrola unikátnosti — ignorujeme vlastní aktuální hodnoty
        if ($this->database->table('users')
            ->where('username', $username)->where('id != ?', $userId)->fetch()) {
            throw new DuplicateNameException('Toto uživatelské jméno je již použito.');
        }
        if ($this->database->table('users')
            ->where('email', $email)->where('id != ?', $userId)->fetch()) {
            throw new DuplicateEmailException('Tento email je již registrován.');
        }

        $data = [
            'username'   => $username,
            'email'      => $email,
            'first_name' => $firstName ?: null,
            'last_name'  => $lastName ?: null,
        ];

        if ($password !== '') {
            $data['password'] = $this->passwords->hash($password);
        }

        $this->database->table('users')->where('id', $userId)->update($data);
    }
}
