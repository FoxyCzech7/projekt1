<?php

namespace App\Components\SignInForm;

interface ISignInFormControlFactory
{
    public function create(): SignInFormControl;
}
