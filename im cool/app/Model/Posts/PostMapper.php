<?php

namespace App\Model\Posts;

use Nette\Database\Table\ActiveRow;

final class PostMapper
{
    public function map(ActiveRow $row): PostDTO
    {
        return new PostDTO(
            id: $row->id,
            title: $row->title,
            content: $row->content,
            createdAt: $row->created_at,
        );
    }
}
