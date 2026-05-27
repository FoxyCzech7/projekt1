<?php

namespace App\Components\ProfileEditForm;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use App\Model\Auth\DuplicateNameException;
use App\Model\Auth\DuplicateEmailException;
use App\Model\Profile\ProfileFacade;

final class ProfileEditFormControl extends Control
{
    public function __construct(
        private ProfileFacade $profileFacade,
    ) {}

    protected function createComponentForm(): Form
    {
        $form = new Form;

        $form->addText('first_name', 'Jméno:');
        $form->addText('last_name', 'Příjmení:');

        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Zadejte uživatelské jméno.');

        $form->addEmail('email', 'E-mail:')
            ->setRequired('Zadejte e-mail.')
            ->addRule(Form::EMAIL, 'Neplatný formát e-mailu.');

        $form->addPassword('password', 'Nové heslo:')
            ->setRequired(false)
            ->setOption('description', 'Vyplňte jen pokud chcete změnit heslo.');

        // potvrzeni hesla je povinne pouze pokud bylo vyplneno pole "Nove heslo"
        $form->addPassword('password_confirm', 'Potvrdit heslo:')
            ->setRequired(false)
            ->addConditionOn($form['password'], Form::Filled)
                ->setRequired('Zadejte potvrzení hesla.')
                ->addRule(Form::Equal, 'Hesla se neshodují.', $form['password']);

        $form->addCheckbox('is_public', 'Veřejný profil (ostatní uvidí moje údaje)');

        $form->addSubmit('send', 'Uložit změny');
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, \stdClass $values): void
    {
        $presenter = $this->getPresenter();
        try {
            $this->profileFacade->updateProfile(
                (int) $presenter->getUser()->getId(),
                $values->username,
                $values->email,
                $values->first_name,
                $values->last_name,
                $values->password,
                $values->is_public,
            );
            $presenter->flashMessage('Profil byl úspěšně aktualizován.', 'success');
            $presenter->redirect('this');
        } catch (DuplicateNameException) {
            $form['username']->addError('Toto uživatelské jméno je již použito.');
        } catch (DuplicateEmailException) {
            $form['email']->addError('Tento email je již registrován.');
        }
    }

    public function setDefaults(array $defaults): void
    {
        $this['form']->setDefaults($defaults);
    }

    public function render(): void
    {
        $this->template->render(__DIR__ . '/ProfileEditFormControl.latte');
    }
}
