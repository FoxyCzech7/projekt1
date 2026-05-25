<?php

namespace App\Presentation\Notifications;

final class NotificationsPresenter extends \App\Presentation\BasePresenter
{
    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }

    public function renderDefault(): void
    {
        $userId = $this->getUser()->getId();
        $this->notificationFacade->markAllRead($userId);
        // zneplatni session cache poctu notifikaci - po markAllRead je spravna hodnota 0
        $this->getSession('notif')->remove();
        $this->template->notifications = $this->notificationFacade->getAll($userId);
    }

    public function actionMarkRead(int $id): void
    {
        $this->notificationFacade->markRead($id);
        $this->redirect('Notifications:default');
    }
}
