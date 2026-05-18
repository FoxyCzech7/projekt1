<?php

namespace App\Model\Premium;

use Nette\Database\Explorer;

/**
 * Správa prémiového předplatného uživatelů.
 * Vyžaduje sloupec premium_until DATETIME NULL v tabulce users.
 */
final class PremiumFacade
{
    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Zjistí, zda má uživatel aktivní prémiové předplatné.
     * Admin má vždy plný přístup — kontrolu role provádí presenter.
     */
    public function isPremium(int $userId): bool
    {
        $user = $this->database->table('users')->get($userId);
        if (!$user) {
            return false;
        }
        $until = $user->premium_until ?? null;
        return $until instanceof \DateTimeInterface && $until > new \DateTimeImmutable();
    }

    /** Vrátí datum vypršení prémiového účtu, nebo null pokud není premium. */
    public function getPremiumUntil(int $userId): ?\DateTimeInterface
    {
        $user = $this->database->table('users')->get($userId);
        $until = $user->premium_until ?? null;
        if ($until instanceof \DateTimeInterface && $until > new \DateTimeImmutable()) {
            return $until;
        }
        return null;
    }

    /**
     * Aktivuje nebo prodlouží prémiové předplatné.
     *
     * Pokud uživatel má ještě aktivní premium, prodloužení začíná od konce
     * stávajícího období (neztrácí zaplacený čas). Jinak začíná od teď.
     *
     * @return \DateTimeImmutable Nové datum vypršení.
     */
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

        return $newExpiry;
    }
}
