<?php

namespace App\Components\EditForm;

interface IEditFormControlFactory
{
    public function create(int $id, string $type): EditFormControl;
}
