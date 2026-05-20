<?php

namespace App\Presentation\Newsletter;

/**
 * Spravuje přihlášení a odhlášení odběru newsletteru.
 * Nevyžaduje vlastní šablonu — každá akce přesměruje zpět na Home.
 *
 * Stav odběru se ukládá do session sekce 'newsletter' (klíč 'email'),
 * aby BasePresenter mohl zobrazit správný stav v patičce bez DB dotazu.
 * Přihlášení k odběru je povoleno pouze pro přihlášené uživatele.
 */
final class NewsletterPresenter extends \App\Presentation\BasePresenter
{
    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');
    }

    /**
     * Zpracuje formulář z patičky (plain HTML POST, ne Nette Form).
     * Validuje email, přihlásí k odběru a uloží do session.
     */
    public function actionSubscribe(): void
    {
        // Newsletter je jen pro přihlášené uživatele — nepřihlášeného přesměrujeme na login
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
            // Uloží email do session → BasePresenter zobrazí "Aktivní odběr" v patičce
            $this->getSession('newsletter')->email = $email;
            $this->flashMessage('Přihlášení k odběru bylo úspěšné.', 'success');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Home:default');
    }

    /**
     * Změní email odběru: odhlásí starý (ze session) a přihlásí nový.
     * Pokud v session není starý email, rovnou přihlásí nový.
     */
    public function actionChange(): void
    {
        $newEmail = trim($this->getHttpRequest()->getPost('email', ''));
        $session  = $this->getSession('newsletter');
        $oldEmail = $session->email ?? '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->flashMessage('Zadejte platný e-mail.', 'error');
            $this->redirect('Home:default');
        }

        // Odhlásí původní email, aby nebyl duplicitní záznam v DB
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

    /**
     * Odhlásí email z odběru a vymaže ho ze session.
     * Email přichází jako URL parametr (z odkazu v patičce nebo z emailu).
     */
    public function actionUnsubscribe(string $email): void
    {
        $this->newsletterFacade->unsubscribe($email);

        // Vymažeme ze session jen pokud odpovídá aktuálně uloženému emailu
        $session = $this->getSession('newsletter');
        if (($session->email ?? '') === $email) {
            unset($session->email);
        }

        $this->flashMessage('Odhlášení z odběru bylo úspěšné.');
        $this->redirect('Home:default');
    }

    /**
     * Admin akce — odešle newsletter o konkrétním příspěvku všem odběratelům.
     * Dostupná pouze pro adminy.
     */
    public function actionSend(int $postId): void
    {
        if (!$this->getUser()->isInRole('admin')) {
            $this->error('Přístup zamítnut.', 403);
        }

        // baseUrl se předá do newsletteru pro sestavení odkazu na článek
        $baseUrl = $this->getHttpRequest()->getUrl()->getBaseUrl();
        $sent = $this->newsletterFacade->sendNewPost($postId, rtrim($baseUrl, '/'));
        $this->flashMessage("Newsletter odeslán {$sent} odběratelům.", 'success');
        $this->redirect('Admin:default');
    }
}
