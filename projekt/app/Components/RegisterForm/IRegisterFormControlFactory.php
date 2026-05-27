<?php

namespace App\Components\RegisterForm;

interface IRegisterFormControlFactory
{
    public function create(): RegisterFormControl;
}
