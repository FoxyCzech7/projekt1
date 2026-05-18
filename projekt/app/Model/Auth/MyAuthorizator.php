<?php

namespace App\Model\Auth;

use Nette\Security\Authorizator;
use Nette\Security\Permission;

final class MyAuthorizator extends Permission implements Authorizator
{
    public function __construct()
    {
        // Role
        $this->addRole('guest');
        $this->addRole('user', 'guest');
        $this->addRole('author', 'user');
        $this->addRole('admin', 'author');

        // Zdroje
        $this->addResource('post');
        $this->addResource('comment');

        // Práva pro guest: jen zobrazování příspěvků a komentářů
        $this->allow('guest', ['post', 'comment'], 'view');

        // User může psát a mazat jen vlastní komentáře, nesmí tvořit příspěvky
        $this->allow('user', 'comment', ['add', 'deleteOwn']);

        // Author může tvořit příspěvky, psát a mazat vlastní příspěvky a komentáře
        $this->allow('author', 'post', ['add', 'deleteOwn']);
        $this->allow('author', 'comment', ['add', 'deleteOwn']);

        // Admin může vše (včetně mazání cizích příspěvků a komentářů)
        $this->allow('admin', self::All, self::All);
    }
}
