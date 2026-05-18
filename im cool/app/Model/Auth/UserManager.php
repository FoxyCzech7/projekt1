<?php

namespace App\Model\Auth;

use Nette;
use Nette\Security\Passwords;
use Nette\Database\Explorer;

/**
 * Správa uživatelů — registrace, vyhledávání, mazání.
 *
 * Tato třída je jediné místo, kde se pracuje s tabulkou users mimo autentizaci.
 * Presentery (zejména SignPresenter) volají tuto třídu místo přímého přístupu k DB.
 */
final class UserManager
{
    use Nette\SmartObject;

    public function __construct(
        private Explorer $database,
        private Passwords $passwords,
    ) {}

    /**
     * Registrace bez emailu (interní použití, např. seed/admin skripty).
     */
    public function register(string $username, string $password, string $role = 'user'): void
    {
        if ($this->exists($username)) {
            throw new DuplicateNameException('Uživatel s tímto jménem již existuje.');
        }

        $this->database->table('users')->insert([
            'username' => $username,
            'password' => $this->passwords->hash($password),
            'role' => $role,
        ]);
    }

    /**
     * Registrace z formuláře — vyžaduje email a hází typované výjimky.
     *
     * Typované výjimky (místo generického \Exception) umožňují presenteru
     * rozlišit příčinu chyby a zobrazit odpovídající chybovou hlášku bez
     * porovnávání textů zpráv.
     */
    public function registerWithEmail(string $username, string $email, string $password, string $role = 'user'): void
    {
        if ($this->exists($username)) {
            throw new DuplicateNameException('Toto uživatelské jméno je již použito.');
        }

        if ($this->existsByEmail($email)) {
            throw new DuplicateEmailException('Tento email je již registrován.');
        }

        $this->database->table('users')->insert([
            'username' => $username,
            'email' => $email,
            'password' => $this->passwords->hash($password),
            'role' => $role,
        ]);
    }

    public function getByUsername(string $username): ?Nette\Database\Table\ActiveRow
    {
        return $this->database->table('users')
            ->where('username', $username)
            ->fetch();
    }

    public function exists(string $username): bool
    {
        return $this->getByUsername($username) !== null;
    }

    public function existsByEmail(string $email): bool
    {
        return $this->database->table('users')->where('email', $email)->fetch() !== null;
    }

    public function getAllUsers(): Nette\Database\Table\Selection
    {
        return $this->database->table('users');
    }

    public function deleteUser(int $id): void
    {
        $this->database->table('users')->where('id', $id)->delete();
    }

    /**
     * Zaznamená čas posledního úspěšného přihlášení.
     * Volá se ihned po User::login() v SignPresenter.
     * Vyžaduje sloupec last_login DATETIME NULL v tabulce users.
     */
    public function updateLastLogin(int $userId): void
    {
        $this->database->table('users')
            ->where('id', $userId)
            ->update(['last_login' => new \DateTimeImmutable()]);
    }
}
