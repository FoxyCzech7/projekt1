<?php

namespace App\Model\Premium;

use Nette\Http\Session;

// Nakupni kosik implementovany pres Nette Sessions
final class CartFacade
{
    private const SECTION = 'premium_cart';

    // Dostupne plany predplatneho.
    private const PLANS = [
        1 => ['id' => 1, 'name' => '1 měsíc',   'months' => 1,  'price' => 99],
        2 => ['id' => 2, 'name' => '3 měsíce',  'months' => 3,  'price' => 249],
        3 => ['id' => 3, 'name' => '6 měsíců',  'months' => 6,  'price' => 449],
        4 => ['id' => 4, 'name' => '12 měsíců', 'months' => 12, 'price' => 799],
    ];

    public function __construct(
        private Session $session,
    ) {}

    // Vrati vsechny dostupne plany
    public function getPlans(): array
    {
        return self::PLANS;
    }

    // Vrati jeden plan podle ID, nebo null pokud neexistuje
    public function getPlanById(int $id): ?array
    {
        return self::PLANS[$id] ?? null;
    }

    // Ulozi vybrany plan do kosiku
    // Prepise predchozi vyber - v kosiku je vzdy jen jeden plan
    public function addToCart(int $planId): void
    {
        $plan = $this->getPlanById($planId);
        if (!$plan) {
            throw new \InvalidArgumentException("Neplatný plán ID: {$planId}");
        }
        $this->session->getSection(self::SECTION)->plan = $plan;
    }

    // Vrati aktualne vybrany plan, nebo null pokud je kosik prazdny
    public function getCart(): ?array
    {
        return $this->session->getSection(self::SECTION)->plan ?? null;
    }

    // Vyprazdni kosik (po dokonceni platby)
    public function clearCart(): void
    {
        $this->session->getSection(self::SECTION)->remove();
    }

    public function isEmpty(): bool
    {
        return $this->getCart() === null;
    }
}
