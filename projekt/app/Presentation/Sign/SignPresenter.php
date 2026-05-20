<?php

namespace App\Presentation\Sign;

use Nette;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use App\Model\Auth\MyAuthenticator;
use App\Model\Auth\UserManager;
use App\Model\Auth\DuplicateNameException;
use App\Model\Auth\DuplicateEmailException;

/**
 * Přihlášení, odhlášení a registrace uživatelů.
 *
 * Pro přihlášení používá MyAuthenticator (ověřuje heslo vůči DB).
 * Pro registraci používá UserManager — presenter tak neví nic o hashování
 * hesel ani o struktuře tabulky users.
 */
final class SignPresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private MyAuthenticator $authenticator,
        private UserManager $userManager,
    ) {}

    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');

        if ($this->getUser()->isLoggedIn() && in_array($this->action, ['in', 'register'], true)) {
            $this->redirect('Home:');
        }
    }

    public function actionOut(): void
    {
        // Vymaže session sekci newsletteru — jinak by zůstal zobrazený "Aktivní odběr"
        // i po přepnutí na jiný účet, protože session je společná pro celý prohlížeč.
        $this->getSession('newsletter')->remove();
        $this->getUser()->logout();
        $this->flashMessage('Odhlášení bylo úspěšné.');
        $this->redirect('Home:');
    }

    protected function createComponentSignInForm(): Form
    {
        $form = new Form;
        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Prosím vyplňte své uživatelské jméno.');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Prosím vyplňte své heslo.');

        $form->addSubmit('send', 'Přihlásit');
        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        return $form;
    }

    public function signInFormSucceeded(Form $form, \stdClass $values): void
    {
        $user = $this->getUser();
        $user->setAuthenticator($this->authenticator);

        try {
            $identity = $this->authenticator->authenticate($values->username, $values->password);
            $user->login($identity);
            // Zaznamenáme čas přihlášení až po úspěšném login(), kdy máme getId().
            $this->userManager->updateLastLogin($user->getId());
            $this->redirect('Home:');
        } catch (AuthenticationException $e) {
            // Záměrně nezobrazujeme původní chybu (neznámý uživatel vs. špatné heslo),
            // aby útočník nemohl zjistit, která jména v systému existují.
            $form->addError('Nesprávné přihlašovací údaje.');
        }
    }

    protected function createComponentRegisterForm(): Form
    {
        $form = new Form;
        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Zadejte uživatelské jméno.');

        $form->addText('email', 'Email:')
            ->setRequired('Zadejte email.')
            ->addRule($form::EMAIL, 'Zadejte platnou emailovou adresu.');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zadejte heslo.');

        $form->addSubmit('send', 'Registrovat');
        $form->onSuccess[] = [$this, 'registerFormSucceeded'];
        return $form;
    }

    public function registerFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->userManager->registerWithEmail($values->username, $values->email, $values->password);
            $this->flashMessage('Registrace byla úspěšná. Nyní se můžete přihlásit.');
            $this->redirect('Sign:in');
        } catch (DuplicateNameException $e) {
            // Každá výjimka = konkrétní chybová hláška bez porovnávání řetězců.
            $form->addError('Toto uživatelské jméno je již použito.');
        } catch (DuplicateEmailException $e) {
            $form->addError('Tento email je již registrován.');
        }
    }
}
