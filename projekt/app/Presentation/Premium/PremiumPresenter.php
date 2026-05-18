<?php

namespace App\Presentation\Premium;

use App\Model\Premium\CartFacade;
use App\Model\Premium\PremiumFacade;
use Nette\Application\UI\Presenter;

/**
 * Správa prémiového předplatného — výběr plánu, košík, platba, potvrzení.
 */
final class PremiumPresenter extends Presenter
{
    public function __construct(
        private CartFacade $cartFacade,
        private PremiumFacade $premiumFacade,
    ) {}

    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');
    }

    /** Přehled dostupných plánů předplatného. */
    public function renderPlans(): void
    {
        $this->template->plans = $this->cartFacade->getPlans();
        $this->template->currentCart = $this->cartFacade->getCart();

        // Pokud je uživatel již premium, zobrazíme kdy mu vyprší.
        $premiumUntil = null;
        if ($this->getUser()->isLoggedIn()) {
            $premiumUntil = $this->premiumFacade->getPremiumUntil($this->getUser()->getId());
        }
        $this->template->premiumUntil = $premiumUntil;
    }

    /** Přidá plán do košíku a přesměruje na košík. */
    public function actionAddToCart(int $planId): void
    {
        $plan = $this->cartFacade->getPlanById($planId);
        if (!$plan) {
            $this->error('Neplatný plán.');
        }
        $this->cartFacade->addToCart($planId);
        $this->redirect('Premium:cart');
    }

    /** Obsah košíku. */
    public function renderCart(): void
    {
        $this->template->cart = $this->cartFacade->getCart();
    }

    /** Odebere položku z košíku a vrátí na výběr plánů. */
    public function actionRemoveFromCart(): void
    {
        $this->cartFacade->clearCart();
        $this->redirect('Premium:plans');
    }

    /**
     * Přechod k platební bráně.
     * Vyžaduje přihlášeného uživatele a neprázdný košík.
     */
    public function actionCheckout(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro dokončení nákupu se prosím přihlaste.', 'info');
            $this->redirect('Sign:in');
        }
        if ($this->cartFacade->isEmpty()) {
            $this->flashMessage('Košík je prázdný.', 'error');
            $this->redirect('Premium:plans');
        }
        $this->redirect('Premium:gateway');
    }

    /**
     * Simulace platební brány — zobrazí loading stránku a automaticky
     * přesměruje na děkovací stránku (simulace okamžitého schválení platby).
     */
    public function renderGateway(): void
    {
        if ($this->cartFacade->isEmpty()) {
            $this->redirect('Premium:plans');
        }
        $this->template->cart = $this->cartFacade->getCart();
        // json_encode zajistí správné escapování pro JS string literal —
        // Latte 3 filtr |json neexistuje, proto serializujeme v PHP.
        $this->template->thankyouUrl = json_encode($this->link('Premium:thankyou'));
    }

    /**
     * Děkovací stránka — aktivuje premium a vymaže košík.
     *
     * Pokud je košík prázdný (uživatel refreshnul stránku), přesměrujeme na plány.
     * Tím zajistíme idempotenci — premium se neaktivuje dvakrát.
     */
    public function renderThankyou(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }

        $cart = $this->cartFacade->getCart();
        if (!$cart) {
            // Košík je prázdný — buď již zpracováno, nebo přišli přímo na URL.
            $this->redirect('Premium:plans');
        }

        $expiry = $this->premiumFacade->activatePremium($this->getUser()->getId(), $cart['months']);
        $this->cartFacade->clearCart();

        $this->template->plan = $cart;
        $this->template->expiry = $expiry;
    }
}
