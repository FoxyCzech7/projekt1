<?php

namespace App\Components\SignInForm;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use App\Model\Auth\MyAuthenticator;
use App\Model\Auth\UserManager;

final class SignInFormControl extends Control
{
    public function __construct(
        private MyAuthenticator $authenticator,
        private UserManager $userManager,
    ) {}

    protected function createComponentForm(): Form
    {
        $form = new Form;
        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Prosím vyplňte své uživatelské jméno.');
        $form->addPassword('password', 'Heslo:')
            ->setRequired('Prosím vyplňte své heslo.');
        $form->addSubmit('send', 'Přihlásit');
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, \stdClass $values): void
    {
        $presenter = $this->getPresenter();
        $user = $presenter->getUser();
        $user->setAuthenticator($this->authenticator);

        try {
            $identity = $this->authenticator->authenticate($values->username, $values->password);
            $user->login($identity);
            $this->userManager->updateLastLogin((int) $user->getId());
            $presenter->redirect('Home:');
        } catch (AuthenticationException $e) {
            $form->addError('Nesprávné přihlašovací údaje.');
        }
    }

    public function render(): void
    {
        $this->template->render(__DIR__ . '/SignInFormControl.latte');
    }
}
