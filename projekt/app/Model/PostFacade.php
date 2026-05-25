<?php

namespace App\Model;

use App\Model\Likes\LikesRepository;
use App\Model\Notifications\NotificationFacade;
use App\Model\Posts\PostsRepository;
use App\Model\Posts\RevisionRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

// Fasada pro operace s prispevky - orchestruje PostsRepository, LikesRepository,
// RevisionRepository a NotificationFacade do jednoho verejneho API pro presentery.
// Presentery nikdy nevolaji jednotliva repository primo.
final class PostFacade
{
    public function __construct(
        private PostsRepository $postsRepository,
        private LikesRepository $likesRepository,
        private RevisionRepository $revisionRepository,
        private NotificationFacade $notificationFacade,
    ) {}

    // Vrati vsechny verejne publikovane prispevky jako Selection (lazy dotaz).
    public function getPublicArticles(): Selection
    {
        return $this->postsRepository->getPublicArticles();
    }

    // Vrati jednu stranku publikovanych prispevku pro strankovani ($offset a $limit).
    public function getPublicArticlesPage(int $offset, int $limit): Selection
    {
        return $this->postsRepository->getPublicArticlesPage($offset, $limit);
    }

    // Vrati celkovy pocet publikovanych prispevku - potrebne pro vypocet poctu stranek.
    public function getPublicArticlesCount(): int
    {
        return $this->postsRepository->getPublicArticlesCount();
    }

    // Najde prispevek podle ID, nebo vrati null.
    public function findById(int $id): ?ActiveRow
    {
        return $this->postsRepository->findById($id);
    }

    // Najde prispevek podle presneho nazvu - pouziva se pro detekci duplikatu pri importu.
    public function findByTitle(string $title): ?ActiveRow
    {
        return $this->postsRepository->findByTitle($title);
    }

    // Vrati rozpracovane prispevky (stav 'draft') daneho uzivatele.
    public function getUserDrafts(int $userId): Selection
    {
        return $this->postsRepository->getUserDrafts($userId);
    }

    // Vrati prispevky s budoucim datem zverejneni - jsou published, ale jeste se nezobrazeji.
    public function getUserScheduled(int $userId): array
    {
        return $this->postsRepository->getUserScheduled($userId);
    }

    // Vytvori novy prispevek; $scheduledAt prepise created_at pro planovane zverejneni.
    public function createPost(
        string $title, string $content, int $userId,
        ?string $image = null, bool $isPremium = false,
        string $status = 'published', ?\DateTimeInterface $scheduledAt = null,
    ): ActiveRow {
        return $this->postsRepository->createPost($title, $content, $userId, $image, $isPremium, $status, $scheduledAt);
    }

    // Aktualizuje prispevek; pokud je zadan $editedBy, pred prepisanim ulozi revizi.
    public function updatePost(
        int $id, string $title, string $content,
        ?string $image = null, ?bool $isPremium = null,
        ?string $status = null, ?\DateTimeInterface $scheduledAt = null,
        ?int $editedBy = null,
    ): void {
        // ulozime revizi pred prepisanim, aby sel prispevek vratit do predchozi verze
        if ($editedBy !== null) {
            $current = $this->postsRepository->findById($id);
            if ($current) {
                $this->revisionRepository->saveRevision($id, $current->title, $current->content, $editedBy);
            }
        }
        $this->postsRepository->updatePost($id, $title, $content, $image, $isPremium, $status, $scheduledAt);
    }

    // Aktualizuje obsah prispevku a vzdy ulozi revizi (zjednodusene API pro EditPresenter).
    public function updatePostContent(int $id, string $title, string $content, int $editedBy): void
    {
        $post = $this->postsRepository->findById($id);
        if ($post) {
            $this->revisionRepository->save($id, $post->title, $post->content, $editedBy);
            $post->update(['title' => $title, 'content' => $content, 'updated_at' => new \DateTimeImmutable()]);
        }
    }

    // Smaze prispevek podle ID - kaskadni smazani zavislych zaznamu resi DB nebo UserProfileFacade.
    public function deletePost(int $id): void
    {
        $this->postsRepository->delete($id);
    }

    // Zvysi pocitadlo zobrazeni prispevku o 1 (vola se pri kazdem renderShow).
    public function incrementViews(int $id): void
    {
        $this->postsRepository->incrementViews($id);
    }

    // Odhaduje dobu cteni v minutach - pocita se rychlosti 200 slov/min, minimum 1 minuta.
    public static function readingTime(string $content): int
    {
        $words = preg_split('/\s+/', trim(strip_tags($content)), -1, PREG_SPLIT_NO_EMPTY);
        return max(1, (int) ceil(count($words) / 200));
    }

    // Vrati historii revizi daneho prispevku serazenou od nejnovejsi.
    public function getRevisions(int $postId): array
    {
        return $this->revisionRepository->getByPost($postId);
    }

    // Prida nebo odebere lajk; notifikuje autora prispevku pokud neni lajkujici sam autor.
    public function toggleLike(int $postId, int $userId): void
    {
        $like = $this->likesRepository->findByUserAndPost($userId, $postId);
        if ($like) {
            // lajk existuje - odebereme ho a snizime pocitadlo
            $like->delete();
            $this->postsRepository->decrementLikes($postId);
        } else {
            // lajk neexistuje - pridame ho a zvysime pocitadlo
            $this->likesRepository->insert(['user_id' => $userId, 'post_id' => $postId]);
            $this->postsRepository->incrementLikes($postId);

            // notifikace autorovi prispevku (ne sobe samemu)
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

    // Vrati mnozinu ID prispevku, ktere dany uzivatel lajknul - pro zobrazeni stavu tlacitka.
    public function getUserLikedPostIds(int $userId, array $postIds): array
    {
        return $this->likesRepository->getUserLikedPostIds($userId, $postIds);
    }
}
