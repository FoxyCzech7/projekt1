<?php

namespace App\Model\Auth;

use Nette;
use Nette\Security\Passwords;
use Nette\Database\Explorer;

// Správa uživatelů — registrace, vyhledávání, mazání a záznam přihlášení.
// Tato třída je jediné místo kde se pracuje s tabulkou 'users' mimo autentizaci.
// Presentery (zejména SignPresenter) volají tuto třídu místo přímého přístupu k DB.
final class UserManager
{
    use Nette\SmartObject;

    public function __construct(
        private Explorer $database,
        private Passwords $passwords,
    ) {}

    // Registrace bez emailu — pro interní použití (seed skripty, admin příkazy).
    // Hází DuplicateNameException pokud uživatelské jméno již existuje.
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

    // Registrace z formuláře — vyžaduje email a hází typované výjimky pro různé příčiny chyb.
    // Typované výjimky (DuplicateNameException, DuplicateEmailException) umožňují presenteru
    // zobrazit přesnou chybovou hlášku bez porovnávání textů zpráv výjimek.
    public function registerWithEmail(string $username, string $email, string $password, string $role = 'user'): void
    {
        if ($this->exists($username)) {
            throw new DuplicateNameException('Toto uživatelské jméno je již použito.');
        }

        if ($this->existsByEmail($email)) {
            throw new DuplicateEmailException('Tento email je již registrován.');
        }

        // Heslo se ukládá jako hash — nikdy jako plaintext.
        $this->database->table('users')->insert([
            'username' => $username,
            'email' => $email,
            'password' => $this->passwords->hash($password),
            'role' => $role,
        ]);
    }

    // Najde uživatele podle uživatelského jména — vrátí řádek nebo null.
    public function getByUsername(string $username): ?Nette\Database\Table\ActiveRow
    {
        return $this->database->table('users')
            ->where('username', $username)
            ->fetch();
    }

    // Zkontroluje, zda uživatelské jméno již existuje v DB.
    public function exists(string $username): bool
    {
        return $this->getByUsername($username) !== null;
    }

    // Zkontroluje, zda email již existuje v DB — pro validaci při registraci.
    public function existsByEmail(string $email): bool
    {
        return $this->database->table('users')->where('email', $email)->fetch() !== null;
    }

    // Vrátí Selection všech uživatelů — pro přehled v admin sekci.
    public function getAllUsers(): Nette\Database\Table\Selection
    {
        return $this->database->table('users');
    }

    // Smaže uživatele podle ID — volat jen po smazání závislých záznamů (viz UserProfileFacade).
    public function deleteUser(int $id): void
    {
        $this->database->table('users')->where('id', $id)->delete();
    }

    // Zaznamená čas posledního úspěšného přihlášení do sloupce last_login.
    // Volá se ihned po User::login() v SignPresenter.
    // Vyžaduje existenci sloupce last_login DATETIME NULL v tabulce users.
    public function updateLastLogin(int $userId): void
    {
        $this->database->table('users')
            ->where('id', $userId)
            ->update(['last_login' => new \DateTimeImmutable()]);
    }
}
