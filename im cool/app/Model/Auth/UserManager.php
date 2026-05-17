<?php

namespace App\Model\Auth;

use Nette;
use Nette\Security\Passwords;
use Nette\Database\Explorer;

final class UserManager
{
	use Nette\SmartObject;

	public function __construct(
		private Explorer $database,
		private Passwords $passwords,
	) {
	}

	/**
	 * Registrace nového uživatele
	 * 
	 * @throws \Exception Pokud uživatel s tímto jménem již existuje
	 */
	public function register(string $username, string $password, string $role = 'user'): void
	{
		if ($this->exists($username)) {
			throw new \Exception('Uživatel s tímto jménem již existuje.');
		}

		$hashedPassword = $this->passwords->hash($password);

		$this->database->table('users')->insert([
			'username' => $username,
			'password' => $hashedPassword,
			'role' => $role,
		]);
	}

	/**
	 * Vrací databázový řádek uživatele podle uživatelského jména
	 */
	public function getByUsername(string $username): ?Nette\Database\Table\ActiveRow
	{
		return $this->database->table('users')
			->where('username', $username)
			->fetch();
	}

	/**
	 * Zjistí, zda uživatel existuje
	 */
	public function exists(string $username): bool
	{
		return $this->getByUsername($username) !== null;
	}

	/**
	 * Vrací všechny uživatele (např. pro administraci)
	 */
	public function getAllUsers(): Nette\Database\Table\Selection
	{
		return $this->database->table('users');
	}

	/**
	 * Smaže uživatele podle ID
	 */
	public function deleteUser(int $id): void
	{
		$this->database->table('users')->where('id', $id)->delete();
	}
}
