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

        // Prava pro guest: jen zobrazovani prispevku a komentaru
        $this->allow('guest', ['post', 'comment'], 'view');

        // User muze psat a mazat jen vlastni komentare, nesmi tvorit prispevky
        $this->allow('user', 'comment', ['add', 'deleteOwn']);

        // Author muze tvorit prispevky, psat a mazat vlastni prispevky a komentare
        $this->allow('author', 'post', ['add', 'deleteOwn']);
        $this->allow('author', 'comment', ['add', 'deleteOwn']);

        // Admin muze vse (vcetne mazani cizich prispevku a komentaru)
        $this->allow('admin', self::All, self::All);
    }
}
