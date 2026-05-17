<?php
namespace App\Model;

use Nette;

final class PostFacade
{
	public function __construct(
		private Nette\Database\Explorer $database,
	) {
	}

	public function getPublicArticles()
	{
		return $this->database
			->table('posts')
			->where('created_at < ', new \DateTime)
			->order('created_at DESC');
	}
	public function getPostDTOs(): array
{
    return array_map(
        fn($row) => $this->postMapper->map($row),
        iterator_to_array($this->postsRepository->getPublicArticles())
    );
}

}