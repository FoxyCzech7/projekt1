<?php

namespace App\Model;

use App\Model\Likes\LikesRepository;
use App\Model\Posts\PostsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Fasáda pro operace s příspěvky.
 *
 * Orchestruje PostsRepository a LikesRepository — presentery
 * volají pouze tuto třídu a neví nic o databázových tabulkách.
 */
final class PostFacade
{
    public function __construct(
        private PostsRepository $postsRepository,
        private LikesRepository $likesRepository,
    ) {}

    public function getPublicArticles(): Selection
    {
        return $this->postsRepository->getPublicArticles();
    }

    public function getPublicArticlesPage(int $offset, int $limit): Selection
    {
        return $this->postsRepository->getPublicArticlesPage($offset, $limit);
    }

    public function getPublicArticlesCount(): int
    {
        return $this->postsRepository->getPublicArticlesCount();
    }

    public function findById(int $id): ?ActiveRow
    {
        return $this->postsRepository->findById($id);
    }

    public function findByTitle(string $title): ?ActiveRow
    {
        return $this->postsRepository->findByTitle($title);
    }

    public function createPost(string $title, string $content, int $userId, ?string $image = null, bool $isPremium = false): ActiveRow
    {
        return $this->postsRepository->createPost($title, $content, $userId, $image, $isPremium);
    }

    public function updatePost(int $id, string $title, string $content, ?string $image = null, ?bool $isPremium = null): void
    {
        $this->postsRepository->updatePost($id, $title, $content, $image, $isPremium);
    }

    /**
     * Aktualizuje obsah příspěvku a zaznamená čas úpravy.
     * Používá EditPresenter — PostFormPresenter naopak volá updatePost(),
     * protože tam může být i nový obrázek.
     */
    public function updatePostContent(int $id, string $title, string $content): void
    {
        $post = $this->postsRepository->findById($id);
        if ($post) {
            $post->update(['title' => $title, 'content' => $content, 'updated_at' => new \DateTimeImmutable()]);
        }
    }

    public function deletePost(int $id): void
    {
        $this->postsRepository->delete($id);
    }

    /**
     * Přidá nebo odebere lajk a aktualizuje počítadlo na příspěvku.
     *
     * Logika je tady (ne v repository), protože operace zasahuje do dvou tabulek:
     * tabulky likes a počítadla v tabulce posts.
     */
    public function toggleLike(int $postId, int $userId): void
    {
        $like = $this->likesRepository->findByUserAndPost($userId, $postId);
        if ($like) {
            $like->delete();
            $this->postsRepository->decrementLikes($postId);
        } else {
            $this->likesRepository->insert(['user_id' => $userId, 'post_id' => $postId]);
            $this->postsRepository->incrementLikes($postId);
        }
    }

    /**
     * Vrátí ID příspěvků, které uživatel lajknul — jako pole [postId => postId].
     *
     * Associativní pole umožňuje O(1) lookup v šabloně: isset($userHasLiked[$post->id]).
     * Parametr $postIds omezuje dotaz jen na příspěvky aktuální stránky.
     */
    public function getUserLikedPostIds(int $userId, array $postIds): array
    {
        return $this->likesRepository->getUserLikedPostIds($userId, $postIds);
    }
}
