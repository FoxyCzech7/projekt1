<?php

namespace App\Components\CommentForm;

interface ICommentFormControlFactory
{
    public function create(int $postId): CommentFormControl;
}
