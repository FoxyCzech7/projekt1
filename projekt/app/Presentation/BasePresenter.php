<?php

namespace App\Presentation;

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

    protected function beforeRender(): void
    {
        parent::beforeRender();
        if ($this->getUser()->isLoggedIn()) {
            $this->template->notifCount = $this->notificationFacade->getUnreadCount($this->getUser()->getId());
        } else {
            $this->template->notifCount = 0;
        }
    }
}
