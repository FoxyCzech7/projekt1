<?php

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

abstract class BaseRepository
{
    public function __construct(
        protected Explorer $database,
    ) {}

    /**
     * Vrátí název databázové tabulky, se kterou tento repository pracuje.
     */
    abstract protected function getTableName(): string;

    /**
     * Vrátí instanci tabulky dle názvu, pro jednodušší opakované použití.
     */
    protected function getTable(): Selection
    {
        return $this->database->table($this->getTableName());
    }

    /**
     * Vrátí všechny záznamy z tabulky jako Selection.
     */
    public function findAll(): Selection
    {
        return $this->getTable();
    }

    /**
     * Vrátí řádek dle ID nebo null, pokud neexistuje.
     */
    public function findById(int $id): ?ActiveRow
    {
        return $this->getTable()->get($id);
    }

    /**
     * Vloží nový záznam do tabulky.
     */
    public function insert(array $data): ActiveRow
    {
        return $this->getTable()->insert($data);
    }

    /**
     * Aktualizuje záznam dle ID.
     */
    public function update(int $id, array $data): void
    {
        $this->getTable()->get($id)?->update($data);
    }

    /**
     * Smaže záznam dle ID.
     */
    public function delete(int $id): void
    {
        $this->getTable()->get($id)?->delete();
    }

    /**
     * Uloží záznam – vloží nový, nebo aktualizuje existující podle ID.
     */
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
