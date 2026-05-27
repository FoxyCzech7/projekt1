<?php

namespace App\Presentation\Sign;

use App\Components\SignInForm\ISignInFormControlFactory;
use App\Components\SignInForm\SignInFormControl;
use App\Components\RegisterForm\IRegisterFormControlFactory;
use App\Components\RegisterForm\RegisterFormControl;

final class SignPresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private ISignInFormControlFactory $signInFormFactory,
        private IRegisterFormControlFactory $registerFormFactory,
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
        // vymaze session sekci newsletteru - jinak by zustal zobrazeny "Aktivni odber"
        // i po prepnuti na jiny ucet, protoze session je spolecna pro cely prohlizec
        $this->getSession('newsletter')->remove();
        $this->getUser()->logout();
        $this->flashMessage('Odhlášení bylo úspěšné.');
        $this->redirect('Home:');
    }

    protected function createComponentSignInForm(): SignInFormControl
    {
        return $this->signInFormFactory->create();
    }

    protected function createComponentRegisterForm(): RegisterFormControl
    {
        return $this->registerFormFactory->create();
    }
}
