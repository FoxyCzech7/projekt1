<?php

namespace App\Components\RegisterForm;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use App\Model\Auth\UserManager;
use App\Model\Auth\DuplicateNameException;
use App\Model\Auth\DuplicateEmailException;

final class RegisterFormControl extends Control
{
    public function __construct(
        private UserManager $userManager,
    ) {}

    protected function createComponentForm(): Form
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
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, \stdClass $values): void
    {
        $presenter = $this->getPresenter();
        try {
            $this->userManager->registerWithEmail($values->username, $values->email, $values->password);
            $presenter->flashMessage('Registrace byla úspěšná. Nyní se můžete přihlásit.');
            $presenter->redirect('Sign:in');
        } catch (DuplicateNameException $e) {
            $form->addError('Toto uživatelské jméno je již použito.');
        } catch (DuplicateEmailException $e) {
            $form->addError('Tento email je již registrován.');
        }
    }

    public function render(): void
    {
        $this->template->render(__DIR__ . '/RegisterFormControl.latte');
    }
}
