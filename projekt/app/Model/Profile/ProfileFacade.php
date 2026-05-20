<?php

namespace App\Model\Profile;

use App\Model\Auth\DuplicateEmailException;
use App\Model\Auth\DuplicateNameException;
use Nette\Database\Explorer;
use Nette\Security\Passwords;

/**
 * Fasáda pro profil přihlášeného uživatele.
 * Statistiky, lajknutý obsah, úprava vlastních údajů a načítání cizích profilů.
 *
 * Pracuje přímo s Explorer (ne přes repository) protože potřebuje
 * cross-table dotazy (JOIN, agregace) které by v repository pattern
 * generovaly zbytečně mnoho dílčích dotazů.
 */
final class ProfileFacade
{
    public function __construct(
        private Explorer $database,
        private Passwords $passwords,
    ) {}

    /**
     * Vrátí agregované statistiky uživatele — počty příspěvků, komentářů a přijatých lajků.
     * COALESCE zajistí 0 místo NULL pokud uživatel nemá žádný obsah.
     */
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

    /**
     * Vrátí příspěvky, které uživatel lajknul.
     * Seřazené od nejnovějšího — JOIN přes tabulku likes.
     */
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

    /**
     * Vrátí komentáře, které uživatel lajknul, včetně názvu příslušného příspěvku.
     */
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
     * Vrátí základní veřejné informace o uživateli pro zobrazení cizího profilu.
     * Vrátí null pokud uživatel s daným ID neexistuje.
     *
     * Pole is_public určuje, zda šablona zobrazí plný profil nebo jen username
     * s hláškou "profil je soukromý".
     */
    public function getPublicProfile(int $userId): ?object
    {
        $user = $this->database->table('users')->get($userId);
        if (!$user) {
            return null;
        }
        return (object) [
            'id'         => $user->id,
            'username'   => $user->username,
            'is_public'  => (bool) ($user->is_public ?? true), // Výchozí true — staré účty bez sloupce jsou veřejné
            'first_name' => $user->first_name ?? null,
            'last_name'  => $user->last_name ?? null,
            'email'      => $user->email ?? null,
        ];
    }

    /**
     * Aktualizuje profil přihlášeného uživatele.
     * Heslo se přehashuje a uloží jen pokud bylo vyplněno (neprázdný řetězec).
     *
     * @throws DuplicateNameException pokud username již používá jiný uživatel
     * @throws DuplicateEmailException pokud email již používá jiný uživatel
     */
    public function updateProfile(int $userId, string $username, string $email,
        string $firstName, string $lastName, string $password, bool $isPublic = true): void
    {
        // Kontrola unikátnosti — podmínka "id != userId" ignoruje vlastní aktuální hodnoty
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
            'first_name' => $firstName ?: null, // Prázdný řetězec → NULL v DB
            'last_name'  => $lastName ?: null,
            'is_public'  => $isPublic ? 1 : 0,
        ];

        // Heslo se aktualizuje pouze pokud uživatel zadal nové — jinak zůstane původní
        if ($password !== '') {
            $data['password'] = $this->passwords->hash($password);
        }

        $this->database->table('users')->where('id', $userId)->update($data);
    }
}
