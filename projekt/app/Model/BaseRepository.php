<?php

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

// Abstraktní základní třída pro všechny repository — definuje společné CRUD operace.
// Každé konkrétní repository musí implementovat getTableName() s názvem své tabulky.
abstract class BaseRepository
{
    public function __construct(
        protected Explorer $database,
    ) {}

    // Vrátí název databázové tabulky, se kterou toto repository pracuje.
    abstract protected function getTableName(): string;

    // Vrátí instanci Selection pro tabulku — používá se interně pro zjednodušení dotazů.
    protected function getTable(): Selection
    {
        return $this->database->table($this->getTableName());
    }

    // Vrátí všechny záznamy z tabulky jako Selection (lazy — data se načtou až při iteraci).
    public function findAll(): Selection
    {
        return $this->getTable();
    }

    // Vrátí jeden řádek podle primárního klíče, nebo null pokud neexistuje.
    public function findById(int $id): ?ActiveRow
    {
        return $this->getTable()->get($id);
    }

    // Vloží nový záznam do tabulky a vrátí vytvořený řádek.
    public function insert(array $data): ActiveRow
    {
        return $this->getTable()->insert($data);
    }

    // Aktualizuje existující záznam podle ID; nic nedělá pokud ID neexistuje.
    public function update(int $id, array $data): void
    {
        $this->getTable()->get($id)?->update($data);
    }

    // Smaže záznam podle ID; nic nedělá pokud ID neexistuje.
    public function delete(int $id): void
    {
        $this->getTable()->get($id)?->delete();
    }

    // Uloží záznam — pokud $id je null, vytvoří nový; pokud existuje, aktualizuje ho.
    // Pokud $id není null ale záznam neexistuje, vloží nový (ochrana před nekonzistencí).
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
