<?php

namespace App\Presentation\Sign;

use Nette;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use App\Model\Auth\MyAuthenticator;

final class SignPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private MyAuthenticator $authenticator,
    ) {}

    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');

        // Přihlášený uživatel nesmí na přihlašovací a registrační stránku
        if ($this->getUser()->isLoggedIn() && in_array($this->action, ['in', 'register'], true)) {
            $this->redirect('Home:');
        }
    }

    public function actionOut(): void
    {
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
            $identity = $this->authenticator->authenticate(
                $values->username,
                $values->password
            );
            $user->login($identity);
            $this->redirect('Home:');
        } catch (AuthenticationException $e) {
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
        $users = $this->authenticator->getDatabase()->table('users');

        if ($users->where('username', $values->username)->fetch()) {
            $form->addError('Toto uživatelské jméno je již použito.');
            return;
        }

        if ($users->where('email', $values->email)->fetch()) {
            $form->addError('Tento email je již registrován.');
            return;
        }

        $hash = $this->authenticator->getPasswords()->hash($values->password);

        $users->insert([
            'username' => $values->username,
            'email' => $values->email,
            'password' => $hash,
            'role' => 'user',
        ]);

        $this->flashMessage('Registrace byla úspěšná. Nyní se můžete přihlásit.');
        $this->redirect('Sign:in');
    }
}
