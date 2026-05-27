<?php

namespace App\Presentation\Newsletter;

// Spravuje prihlaseni a odhlaseni odberu newsletteru
final class NewsletterPresenter extends \App\Presentation\BasePresenter
{
    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');
    }

    // Zpracuje formular z paticce (plain HTML POST, ne Nette Form).
    // Validuje email, prihlas k odberu a ulozi do session
    public function actionSubscribe(): void
    {
        // newsletter je jen pro prihlasene uzivatele - neprihlaseneho presmerujeme na login
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }

        $email = trim($this->getHttpRequest()->getPost('email', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flashMessage('Zadejte platný e-mail.', 'error');
            $this->redirect('Home:default');
        }

        try {
            $this->newsletterFacade->subscribe($email);
            // ulozi email do session → BasePresenter zobrazi "Aktivni odber" v paticce
            $this->getSession('newsletter')->email = $email;
            $this->flashMessage('Přihlášení k odběru bylo úspěšné.', 'success');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Home:default');
    }

    // Zmeni email odberu: odhlas stary (ze session) a prihlas novy
    // Pokud v session neni stary email, rovnou prihlas novy
    public function actionChange(): void
    {
        $newEmail = trim($this->getHttpRequest()->getPost('email', ''));
        $session  = $this->getSession('newsletter');
        $oldEmail = $session->email ?? '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->flashMessage('Zadejte platný e-mail.', 'error');
            $this->redirect('Home:default');
        }

        // odhlas puvodni email, aby nebyl duplicitni zaznam v DB
        if ($oldEmail !== '') {
            $this->newsletterFacade->unsubscribe($oldEmail);
        }

        try {
            $this->newsletterFacade->subscribe($newEmail);
            $session->email = $newEmail;
            $this->flashMessage('E-mail pro odběr byl změněn.', 'success');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Home:default');
    }

    // Odhlas email z odberu a vymaze ho ze session
    // Email prichazi jako URL parametr (z odkazu v paticce nebo z emailu)
    public function actionUnsubscribe(string $email): void
    {
        $this->newsletterFacade->unsubscribe($email);

        // vymaze ze session jen pokud odpovida aktualne ulozenemu emailu
        $session = $this->getSession('newsletter');
        if (($session->email ?? '') === $email) {
            unset($session->email);
        }

        $this->flashMessage('Odhlášení z odběru bylo úspěšné.');
        $this->redirect('Home:default');
    }

    // Admin akce - odesle newsletter o konkretnim prispevku vsem odberatelum
    // Dostupna pouze pro adminy.
    public function actionSend(int $postId): void
    {
        if (!$this->getUser()->isInRole('admin')) {
            $this->error('Přístup zamítnut.', 403);
        }

        $sent = $this->newsletterFacade->sendNewPost($postId);
        $this->flashMessage("Newsletter odeslán {$sent} odběratelům.", 'success');
        $this->redirect('Admin:default');
    }
}
