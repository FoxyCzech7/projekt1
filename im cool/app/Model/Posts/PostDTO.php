<?php

namespace App\Model\Posts;

final class PostDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public \DateTimeInterface $createdAt,
    ) {}
}
