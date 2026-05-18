<?php

namespace App\Model\Premium;

use Nette\Http\Session;

/**
 * Nákupní košík implementovaný přes Nette Sessions.
 *
 * Košík obsahuje vždy nejvýše jeden plán (předplatné nelze kombinovat).
 * Data přežijí request díky session, ale ne přihlášení jiného uživatele.
 */
final class CartFacade
{
    private const SECTION = 'premium_cart';

    /** Dostupné plány předplatného. */
    private const PLANS = [
        1 => ['id' => 1, 'name' => '1 měsíc',   'months' => 1,  'price' => 99],
        2 => ['id' => 2, 'name' => '3 měsíce',  'months' => 3,  'price' => 249],
        3 => ['id' => 3, 'name' => '6 měsíců',  'months' => 6,  'price' => 449],
        4 => ['id' => 4, 'name' => '12 měsíců', 'months' => 12, 'price' => 799],
    ];

    public function __construct(
        private Session $session,
    ) {}

    /** Vrátí všechny dostupné plány. */
    public function getPlans(): array
    {
        return self::PLANS;
    }

    /** Vrátí jeden plán podle ID, nebo null pokud neexistuje. */
    public function getPlanById(int $id): ?array
    {
        return self::PLANS[$id] ?? null;
    }

    /**
     * Uloží vybraný plán do košíku.
     * Přepíše předchozí výběr — v košíku je vždy jen jeden plán.
     */
    public function addToCart(int $planId): void
    {
        $plan = $this->getPlanById($planId);
        if (!$plan) {
            throw new \InvalidArgumentException("Neplatný plán ID: {$planId}");
        }
        $this->session->getSection(self::SECTION)->plan = $plan;
    }

    /** Vrátí aktuálně vybraný plán, nebo null pokud je košík prázdný. */
    public function getCart(): ?array
    {
        return $this->session->getSection(self::SECTION)->plan ?? null;
    }

    /** Vyprázdní košík (po dokončení platby). */
    public function clearCart(): void
    {
        $this->session->getSection(self::SECTION)->remove();
    }

    public function isEmpty(): bool
    {
        return $this->getCart() === null;
    }
}
