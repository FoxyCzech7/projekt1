<?php

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

// Abstraktni zakladni trida pro vsechna repository - definuje spolecne CRUD operace
// Kazde konkretni repository musi implementovat getTableName() s nazvem sve tabulky
abstract class BaseRepository
{
    public function __construct(
        protected Explorer $database,
    ) {}

    // Vrati nazev databazove tabulky, se kterou toto repository pracuje
    abstract protected function getTableName(): string;

    // Vrati instanci Selection pro tabulku - pouziva se interně pro zjednoduseni dotazu
    protected function getTable(): Selection
    {
        return $this->database->table($this->getTableName());
    }

    // Vrati vsechny zaznamy z tabulky jako Selection (lazy - data se nactou az pri iteraci)
    public function findAll(): Selection
    {
        return $this->getTable();
    }

    // Vrati jeden radek podle primarniho klice, nebo null pokud neexistuje.
    public function findById(int $id): ?ActiveRow
    {
        return $this->getTable()->get($id);
    }

    // Vlozi novy zaznam do tabulky a vrati vytvoreny radek
    public function insert(array $data): ActiveRow
    {
        return $this->getTable()->insert($data);
    }

    // Aktualizuje existujici zaznam podle ID; nic nedela pokud ID neexistuje
    public function update(int $id, array $data): void
    {
        $this->getTable()->get($id)?->update($data);
    }

    // Smaze zaznam podle ID; nic nedela pokud ID neexistuje
    public function delete(int $id): void
    {
        $this->getTable()->get($id)?->delete();
    }

    // Ulozi zaznam - pokud $id je null, vytvori novy; pokud existuje, aktualizuje ho
    // Pokud $id neni null ale zaznam neexistuje, vlozi novy
    public function save(?int $id, array $data): ActiveRow
    {
        if ($id === null) {
            return $this->insert($data);
        }

        $row = $this->findById($id);
        if ($row) {
            $row->update($data);
            return $row;
        }

        return $this->insert($data);
    }
}
