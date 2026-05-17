<?php

namespace App\Model\Auth;

use Nette;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\Identity;
use Nette\Security\AuthenticationException;

final class MyAuthenticator implements Authenticator
{
    use Nette\SmartObject;

    public function __construct(
        private Nette\Database\Explorer $database,
        private Nette\Security\Passwords $passwords,
    ) {}

    public function authenticate(string|array $credentials, string $password): IIdentity
    {
        $username = is_array($credentials) ? ($credentials['username'] ?? null) : $credentials;
        if (!$username) {
            throw new AuthenticationException('Uživatelské jméno nebylo zadáno.');
        }

        $user = $this->database->table('users')
            ->where('username', $username)
            ->fetch();

        if (!$user) {
            throw new AuthenticationException('Uživatel nenalezen.');
        }

        if (!$this->passwords->verify($password, $user->password)) {
            throw new AuthenticationException('Nesprávné heslo.');
        }

        return new Identity($user->id, $user->role, ['username' => $user->username]);
    }

    // ⬇️ Tyto metody umožní SignPresenteru používat databázi a hashování
    public function getDatabase(): Nette\Database\Explorer
    {
        return $this->database;
    }

    public function getPasswords(): Nette\Security\Passwords
    {
        return $this->passwords;
    }
}
