<?php

namespace App\Presentation;

use App\Model\Newsletter\NewsletterFacade;
use App\Model\Notifications\NotificationFacade;
use Nette\Application\UI\Presenter;

// Zakladni presenter - nastavuje promenne spolecne pro vsechny sablony.
// Kazdy presenter dedi z teto tridy misto primo z Presenter.
// Pouziva @inject misto konstruktoru, aby podtridy mohly mit vlastni
// konstruktory bez nutnosti volat parent::__construct() s parametry.
abstract class BasePresenter extends Presenter
{
    // @inject - Nette DI nastavi tuto property automaticky pred startup()
    // Pouzivame public, protoze Nette DI nemuze nastavit private/protected property.
    /** @inject */
    public NotificationFacade $notificationFacade;

    /** @inject */
    public NewsletterFacade $newsletterFacade;

    protected function beforeRender(): void
    {
        parent::beforeRender();

        // pocet neprectenych notifikaci - cachovano v session (TTL 30 s), aby se DB nedotazovala pri kazdem requestu
        if ($this->getUser()->isLoggedIn()) {
            $notifSession = $this->getSession('notif');
            if (!isset($notifSession->count) || (time() - ($notifSession->ts ?? 0)) > 30) {
                $notifSession->count = $this->notificationFacade->getUnreadCount($this->getUser()->getId());
                $notifSession->ts    = time();
            }
            $this->template->notifCount = $notifSession->count;
        } else {
            $this->template->notifCount = 0;
        }

        // zjisti, zda je aktualni uzivatel prihlasen k odberu newsletteru.
        // primarně cte ze session (ulozeno pri subscribe/change).
        // fallback pro prihlasene uzivatele: zkontroluje jejich ucet email v DB -
        // pokryje pripad kdy prihlasili odber z jineho zarizeni/prohlizece.
        $session = $this->getSession('newsletter');
        $subscribedEmail = $session->email ?? null;

        if ($subscribedEmail === null && $this->getUser()->isLoggedIn()) {
            $identity  = $this->getUser()->getIdentity();
            $userEmail = $identity ? ($identity->email ?? null) : null;
            if ($userEmail && $this->newsletterFacade->isSubscribed($userEmail)) {
                $subscribedEmail = $userEmail;
                $session->email  = $userEmail; // ulozi do session, aby se DB nedotazovala pri kazdem requestu
            }
        }

        $this->template->subscribedEmail = $subscribedEmail;
    }
}
