<?php

namespace App\Model\Auth;

use Nette;
use Nette\Security\Passwords;
use Nette\Database\Explorer;

// Sprava uzivatelu, registrace, vyhledavani, mazani a zaznam prihlaseni.
// Tato trida je jedine misto kde se pracuje s tabulkou 'users' mimo autentizaci.
// Presentery (hlavne SignPresenter) volaji tuto tridu misto primého pristupu k DB.
final class UserManager
{
    use Nette\SmartObject;

    public function __construct(
        private Explorer $database,
        private Passwords $passwords,
    ) {}

    // Registrace bez emailu - pro interni pouziti (seed skripty, admin prikazy).
    // Hazi DuplicateNameException pokud uzivatelske jmeno uz existuje.
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

    // Registrace z formulare - vyzaduje email a hazi typovane vyjimky pro ruzne priciny chyb.
    // Typovane vyjimky (DuplicateNameException, DuplicateEmailException) umoznuji presenteru
    // zobrazit presnou chybovou hlasku bez porovnavani textu zprav vyjimek.
    public function registerWithEmail(string $username, string $email, string $password, string $role = 'user'): void
    {
        if ($this->exists($username)) {
            throw new DuplicateNameException('Toto uživatelské jméno je již použito.');
        }

        if ($this->existsByEmail($email)) {
            throw new DuplicateEmailException('Tento email je již registrován.');
        }

        // heslo se uklada jako hash - nikdy jako plaintext
        $this->database->table('users')->insert([
            'username' => $username,
            'email'    => $email,
            'password' => $this->passwords->hash($password),
            'role'     => $role,
        ]);
    }

    // Najde uzivatele podle uzivatelského jmena - vrati radek nebo null.
    public function getByUsername(string $username): ?Nette\Database\Table\ActiveRow
    {
        return $this->database->table('users')
            ->where('username', $username)
            ->fetch();
    }

    // Zkontroluje, zda uzivatelske jmeno uz existuje v DB.
    public function exists(string $username): bool
    {
        return $this->getByUsername($username) !== null;
    }

    // Zkontroluje, zda email uz existuje v DB - pro validaci pri registraci.
    public function existsByEmail(string $email): bool
    {
        return $this->database->table('users')->where('email', $email)->fetch() !== null;
    }

    // Vrati Selection vsech uzivatelu - pro prehled v admin sekci.
    public function getAllUsers(): Nette\Database\Table\Selection
    {
        return $this->database->table('users');
    }

    // Smaze uzivatele podle ID - volat jen po smazani zavislych zaznamu (viz UserProfileFacade).
    public function deleteUser(int $id): void
    {
        $this->database->table('users')->where('id', $id)->delete();
    }

    // Zaznamena cas posledniho uspesneho prihlaseni do sloupce last_login.
    // Vola se ihned po User::login() v SignPresenter.
    // Vyzaduje existenci sloupce last_login DATETIME NULL v tabulce users.
    public function updateLastLogin(int $userId): void
    {
        $this->database->table('users')
            ->where('id', $userId)
            ->update(['last_login' => new \DateTimeImmutable()]);
    }
}
