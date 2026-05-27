<?php

namespace App\Components\PostForm;

interface IPostFormControlFactory
{
    public function create(?int $postId): PostFormControl;
}
