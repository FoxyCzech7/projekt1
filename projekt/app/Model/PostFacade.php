<?php

namespace App\Model;

use App\Model\Likes\LikesRepository;
use App\Model\Notifications\NotificationFacade;
use App\Model\Posts\PostsRepository;
use App\Model\Posts\RevisionRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

// Fasáda pro operace s příspěvky — orchestruje PostsRepository, LikesRepository,
// RevisionRepository a NotificationFacade do jednoho veřejného API pro presentery.
// Presentery nikdy nevolají jednotlivá repository přímo.
final class PostFacade
{
    public function __construct(
        private PostsRepository $postsRepository,
        private LikesRepository $likesRepository,
        private RevisionRepository $revisionRepository,
        private NotificationFacade $notificationFacade,
    ) {}

    // Vrátí všechny veřejně publikované příspěvky jako Selection (lazy dotaz).
    public function getPublicArticles(): Selection
    {
        return $this->postsRepository->getPublicArticles();
    }

    // Vrátí jednu stránku publikovaných příspěvků pro stránkování ($offset a $limit).
    public function getPublicArticlesPage(int $offset, int $limit): Selection
    {
        return $this->postsRepository->getPublicArticlesPage($offset, $limit);
    }

    // Vrátí celkový počet publikovaných příspěvků — potřebné pro výpočet počtu stránek.
    public function getPublicArticlesCount(): int
    {
        return $this->postsRepository->getPublicArticlesCount();
    }

    // Najde příspěvek podle ID, nebo vrátí null.
    public function findById(int $id): ?ActiveRow
    {
        return $this->postsRepository->findById($id);
    }

    // Najde příspěvek podle přesného názvu — používá se pro detekci duplikátů při importu.
    public function findByTitle(string $title): ?ActiveRow
    {
        return $this->postsRepository->findByTitle($title);
    }

    // Vrátí rozpracované příspěvky (stav 'draft') daného uživatele.
    public function getUserDrafts(int $userId): Selection
    {
        return $this->postsRepository->getUserDrafts($userId);
    }

    // Vrátí příspěvky s budoucím datem zveřejnění — jsou published, ale ještě se nezobrazují.
    public function getUserScheduled(int $userId): array
    {
        return $this->postsRepository->getUserScheduled($userId);
    }

    // Vytvoří nový příspěvek; $scheduledAt přepíše created_at pro plánované zveřejnění.
    public function createPost(
        string $title, string $content, int $userId,
        ?string $image = null, bool $isPremium = false,
        string $status = 'published', ?\DateTimeInterface $scheduledAt = null,
    ): ActiveRow {
        return $this->postsRepository->createPost($title, $content, $userId, $image, $isPremium, $status, $scheduledAt);
    }

    // Aktualizuje příspěvek; pokud je zadán $editedBy, před přepsáním uloží revizi.
    public function updatePost(
        int $id, string $title, string $content,
        ?string $image = null, ?bool $isPremium = null,
        ?string $status = null, ?\DateTimeInterface $scheduledAt = null,
        ?int $editedBy = null,
    ): void {
        // Uložíme revizi před přepsáním, aby šlo příspěvek vrátit do předchozí verze.
        if ($editedBy !== null) {
            $current = $this->postsRepository->findById($id);
            if ($current) {
                $this->revisionRepository->saveRevision($id, $current->title, $current->content, $editedBy);
            }
        }
        $this->postsRepository->updatePost($id, $title, $content, $image, $isPremium, $status, $scheduledAt);
    }

    // Aktualizuje obsah příspěvku a vždy uloží revizi (zjednodušené API pro EditPresenter).
    public function updatePostContent(int $id, string $title, string $content, int $editedBy): void
    {
        $post = $this->postsRepository->findById($id);
        if ($post) {
            $this->revisionRepository->save($id, $post->title, $post->content, $editedBy);
            $post->update(['title' => $title, 'content' => $content, 'updated_at' => new \DateTimeImmutable()]);
        }
    }

    // Smaže příspěvek podle ID — kaskádní smazání závislých záznamů řeší DB nebo UserProfileFacade.
    public function deletePost(int $id): void
    {
        $this->postsRepository->delete($id);
    }

    // Zvýší počitadlo zobrazení příspěvku o 1 (volá se při každém renderShow).
    public function incrementViews(int $id): void
    {
        $this->postsRepository->incrementViews($id);
    }

    // Odhaduje dobu čtení v minutách — počítá se rychlostí 200 slov/min, minimum 1 minuta.
    public static function readingTime(string $content): int
    {
        $words = preg_split('/\s+/', trim(strip_tags($content)), -1, PREG_SPLIT_NO_EMPTY);
        return max(1, (int) ceil(count($words) / 200));
    }

    // Vrátí historii revizí daného příspěvku seřazenou od nejnovější.
    public function getRevisions(int $postId): array
    {
        return $this->revisionRepository->getByPost($postId);
    }

    // Přidá nebo odebere lajk; notifikuje autora příspěvku pokud není lajkující sám autor.
    public function toggleLike(int $postId, int $userId): void
    {
        $like = $this->likesRepository->findByUserAndPost($userId, $postId);
        if ($like) {
            // Lajk existuje — odebereme ho a snížíme počitadlo.
            $like->delete();
            $this->postsRepository->decrementLikes($postId);
        } else {
            // Lajk neexistuje — přidáme ho a zvýšíme počitadlo.
            $this->likesRepository->insert(['user_id' => $userId, 'post_id' => $postId]);
            $this->postsRepository->incrementLikes($postId);

            // Notifikace autorovi příspěvku (ne sobě samému)
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

    // Vrátí množinu ID příspěvků, které daný uživatel lajknul — pro zobrazení stavu tlačítka.
    public function getUserLikedPostIds(int $userId, array $postIds): array
    {
        return $this->likesRepository->getUserLikedPostIds($userId, $postIds);
    }
}
