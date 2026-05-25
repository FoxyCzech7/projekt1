<?php

namespace App\Model\Profile;

use App\Model\Auth\DuplicateEmailException;
use App\Model\Auth\DuplicateNameException;
use Nette\Database\Explorer;
use Nette\Security\Passwords;

// Fasada pro profil prihlaseneho uzivatele.
// Statistiky, lajknuty obsah, uprava vlastnich udaju a nacitani cizich profilu
// Pracuje primo s Explorer (ne pres repository) protoze potrebuje
// cross-table dotazy (JOIN, agregace) ktere by v repository pattern
// generovaly zbytecne mnoho dilcich dotazu
final class ProfileFacade
{
    public function __construct(
        private Explorer $database,
        private Passwords $passwords,
    ) {}

    // Vrati agregovane statistiky uzivatele - pocty prispevku, komentaru a prijatych lajku
    // Jeden SQL dotaz s poddotazy misto 4 separatnich dotazu
    public function getStats(int $userId): array
    {
        return (array) $this->database->query(
            'SELECT
                (SELECT COUNT(*)              FROM posts    WHERE user_id = ?) AS post_count,
                (SELECT COUNT(*)              FROM comments WHERE user_id = ?) AS comment_count,
                (SELECT COALESCE(SUM(likes_count),0) FROM posts    WHERE user_id = ?) AS post_likes_received,
                (SELECT COALESCE(SUM(likes_count),0) FROM comments WHERE user_id = ?) AS comment_likes_received',
            $userId, $userId, $userId, $userId
        )->fetch();
    }

    // Vrati prispevky, ktere uzivatel lajknul
    // Serazene od nejnovejsiho - JOIN pres tabulku likes
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

    // Vrati komentare, ktere uzivatel lajknul, vcetne nazvu prislusneho prispevku
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

    // Vrati zakladni verejne informace o uzivateli pro zobrazeni ciziho profilu
    // Vrati null pokud uzivatel s danym ID neexistuje
    // Pole is_public urcuje, zda sablona zobrazi plny profil nebo jen username
    // s hlaskou "profil je soukromy"
    public function getPublicProfile(int $userId): ?object
    {
        $user = $this->database->table('users')->get($userId);
        if (!$user) {
            return null;
        }
        return (object) [
            'id'         => $user->id,
            'username'   => $user->username,
            'is_public'  => (bool) ($user->is_public ?? true), // vychozi true - stare ucty bez sloupce jsou verejne
            'first_name' => $user->first_name ?? null,
            'last_name'  => $user->last_name ?? null,
            'email'      => $user->email ?? null,
        ];
    }

    // Aktualizuje profil prihlaseneho uzivatele
    // Heslo se prehashuje a ulozi jen pokud bylo vyplneno (neprazdny retezec)
    // Hazi DuplicateNameException pokud username uz pouziva jiny uzivatel
    // Hazi DuplicateEmailException pokud email uz pouziva jiny uzivatel
    public function updateProfile(int $userId, string $username, string $email,
        string $firstName, string $lastName, string $password, bool $isPublic = true): void
    {
        // podminka "id != userId" ignoruje vlastni aktualni hodnoty
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
            'first_name' => $firstName ?: null, // prazdny retezec → NULL v DB
            'last_name'  => $lastName ?: null,
            'is_public'  => $isPublic ? 1 : 0,
        ];

        // heslo se aktualizuje pouze pokud uzivatel zadal nove - jinak zustane puvodni
        if ($password !== '') {
            $data['password'] = $this->passwords->hash($password);
        }

        $this->database->table('users')->where('id', $userId)->update($data);
    }
}
