<?php

namespace App\Model\Premium;

use Nette\Database\Explorer;

// Sprava premioveho predplatneho uzivatelu.
// Vyzaduje sloupec premium_until DATETIME NULL v tabulce users.
final class PremiumFacade
{
    // request-level cache pro premium_until, aby se DB nedotazovala vicekrat za request
    private array $cache = [];

    public function __construct(
        private Explorer $database,
    ) {}

    private function fetchUntil(int $userId): ?\DateTimeInterface
    {
        if (!array_key_exists($userId, $this->cache)) {
            $user  = $this->database->table('users')->get($userId);
            $until = $user ? ($user->premium_until ?? null) : null;
            $this->cache[$userId] = $until instanceof \DateTimeInterface ? $until : null;
        }
        return $this->cache[$userId];
    }

    // Zjisti, zda ma uzivatel aktivni premiove predplatne.
    // Admin ma vzdy plny pristup - kontrolu role provadi presenter.
    public function isPremium(int $userId): bool
    {
        $until = $this->fetchUntil($userId);
        return $until !== null && $until > new \DateTimeImmutable();
    }

    // Vrati datum vyprseni premioveho uctu, nebo null pokud neni premium.
    public function getPremiumUntil(int $userId): ?\DateTimeInterface
    {
        $until = $this->fetchUntil($userId);
        return ($until !== null && $until > new \DateTimeImmutable()) ? $until : null;
    }

    // Aktivuje nebo prodlouzi premiove predplatne.
    // Pokud uzivatel ma jeste aktivni premium, prodlouzeni zacina od konce
    // stavajiciho obdobi (neztrati zaplaceny cas). Jinak zacina od ted.
    // Vrati nove datum vyprseni.
    public function activatePremium(int $userId, int $months): \DateTimeImmutable
    {
        $user = $this->database->table('users')->get($userId);
        if (!$user) {
            throw new \RuntimeException('Uživatel nenalezen.');
        }

        $until = $user->premium_until ?? null;
        $base = ($until instanceof \DateTimeInterface && $until > new \DateTimeImmutable())
            ? \DateTimeImmutable::createFromInterface($until)
            : new \DateTimeImmutable();

        $newExpiry = $base->modify("+{$months} months");

        $this->database->table('users')
            ->where('id', $userId)
            ->update(['premium_until' => $newExpiry]);

        unset($this->cache[$userId]); // zneplatni cache po zmene
        return $newExpiry;
    }
}
