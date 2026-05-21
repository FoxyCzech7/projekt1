<?php

namespace App\Presentation;

use App\Model\Newsletter\NewsletterFacade;
use App\Model\Notifications\NotificationFacade;
use Nette\Application\UI\Presenter;

/**
 * Základní presenter — nastavuje proměnné společné pro všechny šablony.
 * Každý presenter dědí z této třídy místo přímo z Presenter.
 *
 * Používá @inject místo konstruktoru, aby podtřídy mohly mít vlastní
 * konstruktory bez nutnosti volat parent::__construct() s parametry.
 */
abstract class BasePresenter extends Presenter
{
    // @inject — Nette DI nastaví tuto property automaticky před startup(). Používáme public, protože Nette DI nemůže nastavit private/protected property.
     /* Díky @inject nemusíme řešit konstruktor a závislosti v potomcích, které často potřebují jiné služby.
     */ 
    public NotificationFacade $notificationFacade;

    /** @inject */
    public NewsletterFacade $newsletterFacade;

    protected function beforeRender(): void
    {
        parent::beforeRender();

        // Počet nepřečtených notifikací — zobrazuje se jako odznak na ikoně zvonku v navigaci
        if ($this->getUser()->isLoggedIn()) {
            $this->template->notifCount = $this->notificationFacade->getUnreadCount($this->getUser()->getId());
        } else {
            $this->template->notifCount = 0;
        }

        // Zjistí, zda je aktuální uživatel přihlášen k odběru newsletteru.
        // Primárně čte ze session (uloženo při subscribe/change).
        // Fallback pro přihlášené uživatele: zkontroluje jejich účtový email v DB —
        // pokryje případ kdy přihlásili odběr z jiného zařízení/prohlížeče.
        $session = $this->getSession('newsletter');
        $subscribedEmail = $session->email ?? null;

        if ($subscribedEmail === null && $this->getUser()->isLoggedIn()) {
            $identity  = $this->getUser()->getIdentity();
            $userEmail = $identity ? ($identity->email ?? null) : null;
            if ($userEmail && $this->newsletterFacade->isSubscribed($userEmail)) {
                $subscribedEmail = $userEmail;
                $session->email  = $userEmail; // Uloží do session, aby se DB nedotazovala při každém requestu
            }
        }

        $this->template->subscribedEmail = $subscribedEmail;
    }
}
