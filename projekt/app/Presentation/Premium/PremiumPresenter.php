<?php

namespace App\Presentation\Premium;

use App\Model\Premium\CartFacade;
use App\Model\Premium\PremiumFacade;
use Nette\Application\UI\Presenter;

// Sprava premioveho predplatneho - vyber planu, kosik, platba, potvrzeni
final class PremiumPresenter extends \App\Presentation\BasePresenter
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

    // Prehled dostupnych planu predplatneho
    public function renderPlans(): void
    {
        $this->template->plans = $this->cartFacade->getPlans();
        $this->template->currentCart = $this->cartFacade->getCart();

        // pokud je uzivatel uz premium, zobrazime kdy mu vyprsi
        $premiumUntil = null;
        if ($this->getUser()->isLoggedIn()) {
            $premiumUntil = $this->premiumFacade->getPremiumUntil($this->getUser()->getId());
        }
        $this->template->premiumUntil = $premiumUntil;
    }

    // Prida plan do kosiku a presmeruje na kosik
    public function actionAddToCart(int $planId): void
    {
        $plan = $this->cartFacade->getPlanById($planId);
        if (!$plan) {
            $this->error('Neplatný plán.');
        }
        $this->cartFacade->addToCart($planId);
        $this->redirect('Premium:cart');
    }

    // Obsah kosiku
    public function renderCart(): void
    {
        $this->template->cart = $this->cartFacade->getCart();
    }

    // Odebere polozku z kosiku a vrati na vyber planu
    public function actionRemoveFromCart(): void
    {
        $this->cartFacade->clearCart();
        $this->redirect('Premium:plans');
    }

    // Prechod k platebni brane
    // Vyzaduje prihlaseneho uzivatele a neprazdny kosik
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

    // Simulace platebni brany - zobrazi loading stranku a automaticky
    // presmeruje na dekovaci stranku (simulace okamziteho schvaleni platby)
    public function renderGateway(): void
    {
        if ($this->cartFacade->isEmpty()) {
            $this->redirect('Premium:plans');
        }
        $this->template->cart = $this->cartFacade->getCart();
        // json_encode zajisti spravne escapovani pro JS string literal
        $this->template->thankyouUrl = json_encode($this->link('Premium:thankyou'));
    }

    // Dekovaci stranka - aktivuje premium a vymaze kosik
    // Pokud je kosik prazdny (uzivatel refreshnul stranku), presmerujeme na plany
    // Tim zajistime idempotenci - premium se neaktivuje dvakrat
    public function renderThankyou(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }

        $cart = $this->cartFacade->getCart();
        if (!$cart) {
            // kosik je prazdny - bud uz zpracovano, nebo prisli primo na URL
            $this->redirect('Premium:plans');
        }

        $expiry = $this->premiumFacade->activatePremium($this->getUser()->getId(), $cart['months']);
        $this->cartFacade->clearCart();

        $this->template->plan = $cart;
        $this->template->expiry = $expiry;
    }
}
