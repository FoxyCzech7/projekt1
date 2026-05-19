<?php

namespace App\Presentation\Newsletter;

use App\Model\Newsletter\NewsletterFacade;

final class NewsletterPresenter extends \App\Presentation\BasePresenter
{
    public function __construct(private NewsletterFacade $newsletterFacade) {}

    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');
    }

    /**
     * Zpracuje plain HTML formulář z patičky (POST s polem 'email').
     * Nepotřebuje šablonu — vždy přesměruje.
     */
    public function actionSubscribe(): void
    {
        $email = trim($this->getHttpRequest()->getPost('email', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flashMessage('Zadejte platný e-mail.', 'error');
            $this->redirect('Home:default');
        }

        try {
            $this->newsletterFacade->subscribe($email);
            $this->flashMessage('Přihlášení k odběru bylo úspěšné.', 'success');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Home:default');
    }

    public function actionUnsubscribe(string $email): void
    {
        $this->newsletterFacade->unsubscribe($email);
        $this->flashMessage('Odhlášení z odběru bylo úspěšné.');
        $this->redirect('Home:default');
    }

    /** Volá admin — odešle newsletter o daném příspěvku. */
    public function actionSend(int $postId): void
    {
        if (!$this->getUser()->isInRole('admin')) {
            $this->error('Přístup zamítnut.', 403);
        }
        $baseUrl = $this->getHttpRequest()->getUrl()->getBaseUrl();
        $sent = $this->newsletterFacade->sendNewPost($postId, rtrim($baseUrl, '/'));
        $this->flashMessage("Newsletter odeslán {$sent} odběratelům.", 'success');
        $this->redirect('Admin:default');
    }
}
