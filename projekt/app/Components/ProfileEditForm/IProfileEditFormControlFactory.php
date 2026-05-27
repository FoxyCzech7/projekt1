<?php

namespace App\Components\ProfileEditForm;

interface IProfileEditFormControlFactory
{
    public function create(): ProfileEditFormControl;
}
