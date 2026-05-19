<?php

namespace App\Model;

use App\Model\Likes\LikesRepository;
use App\Model\Notifications\NotificationFacade;
use App\Model\Posts\PostsRepository;
use App\Model\Posts\RevisionRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class PostFacade
{
    public function __construct(
        private PostsRepository $postsRepository,
        private LikesRepository $likesRepository,
        private RevisionRepository $revisionRepository,
        private NotificationFacade $notificationFacade,
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

    public function getUserDrafts(int $userId): Selection
    {
        return $this->postsRepository->getUserDrafts($userId);
    }

    public function createPost(
        string $title, string $content, int $userId,
        ?string $image = null, bool $isPremium = false,
        string $status = 'published', ?\DateTimeInterface $scheduledAt = null,
    ): ActiveRow {
        return $this->postsRepository->createPost($title, $content, $userId, $image, $isPremium, $status, $scheduledAt);
    }

    public function updatePost(
        int $id, string $title, string $content,
        ?string $image = null, ?bool $isPremium = null,
        ?string $status = null, ?\DateTimeInterface $scheduledAt = null,
        ?int $editedBy = null,
    ): void {
        // Uložíme revizi před přepsáním
        if ($editedBy !== null) {
            $current = $this->postsRepository->findById($id);
            if ($current) {
                $this->revisionRepository->saveRevision($id, $current->title, $current->content, $editedBy);
            }
        }
        $this->postsRepository->updatePost($id, $title, $content, $image, $isPremium, $status, $scheduledAt);
    }

    public function updatePostContent(int $id, string $title, string $content, int $editedBy): void
    {
        $post = $this->postsRepository->findById($id);
        if ($post) {
            $this->revisionRepository->save($id, $post->title, $post->content, $editedBy);
            $post->update(['title' => $title, 'content' => $content, 'updated_at' => new \DateTimeImmutable()]);
        }
    }

    public function deletePost(int $id): void
    {
        $this->postsRepository->delete($id);
    }

    public function incrementViews(int $id): void
    {
        $this->postsRepository->incrementViews($id);
    }

    /** Odhadovaný čas čtení v minutách (200 slov/min). */
    public static function readingTime(string $content): int
    {
        $words = preg_split('/\s+/', trim(strip_tags($content)), -1, PREG_SPLIT_NO_EMPTY);
        return max(1, (int) ceil(count($words) / 200));
    }

    public function getRevisions(int $postId): array
    {
        return $this->revisionRepository->getByPost($postId);
    }

    /**
     * Toggle lajku — notifikuje autora příspěvku (pokud není lajkující sám autor).
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

            // Notifikace autorovi příspěvku
            $post = $this->postsRepository->findById($postId);
            if ($post && $post->user_id !== $userId) {
                $this->notificationFacade->create(
                    $post->user_id,
                    'post_like',
                    'Někdo lajknul váš příspěvek „' . mb_substr($post->title, 0, 60) . '"',
                    '/post/show/' . $postId,
                );
            }
        }
    }

    public function getUserLikedPostIds(int $userId, array $postIds): array
    {
        return $this->likesRepository->getUserLikedPostIds($userId, $postIds);
    }
}
