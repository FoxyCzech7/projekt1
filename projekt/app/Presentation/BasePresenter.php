<?php

namespace App\Presentation;

use App\Model\Newsletter\NewsletterFacade;
use App\Model\Notifications\NotificationFacade;
use Nette\Application\UI\Presenter;

/**
 * Základní presenter — nastavuje proměnné společné pro všechny šablony.
 * Každý presenter dědí z této třídy místo přímo z Presenter.
 */
abstract class BasePresenter extends Presenter
{
    /** @inject */
    public NotificationFacade $notificationFacade;

    /** @inject */
    public NewsletterFacade $newsletterFacade;

    protected function beforeRender(): void
    {
        parent::beforeRender();

        if ($this->getUser()->isLoggedIn()) {
            $this->template->notifCount = $this->notificationFacade->getUnreadCount($this->getUser()->getId());
        } else {
            $this->template->notifCount = 0;
        }

        $session = $this->getSession('newsletter');
        $subscribedEmail = $session->email ?? null;

        // Pro přihlášené uživatele: fallback přes jejich účtový email
        if ($subscribedEmail === null && $this->getUser()->isLoggedIn()) {
            $identity  = $this->getUser()->getIdentity();
            $userEmail = $identity ? ($identity->email ?? null) : null;
            if ($userEmail && $this->newsletterFacade->isSubscribed($userEmail)) {
                $subscribedEmail  = $userEmail;
                $session->email   = $userEmail;
            }
        }

        $this->template->subscribedEmail = $subscribedEmail;
    }
}
