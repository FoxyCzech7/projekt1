<?php
namespace App\Presentation\Home;

use App\Model\Posts\PostsRepository;
use Nette\Application\UI\Presenter;
use Nette\Database\Explorer;
use Nette\Utils\Paginator;

final class HomePresenter extends Presenter
{
    public function __construct(
        private PostsRepository $postsRepository,
        private Explorer $database,
    ) {
        parent::__construct();
    }

    public function renderDefault(int $page = 1): void
    {
        $itemsPerPage = 5;

        $paginator = new Paginator();
        $paginator->setItemsPerPage($itemsPerPage);
        $paginator->setPage($page);

        $totalPosts = $this->postsRepository->getPublicArticlesCount();
        $paginator->setItemCount($totalPosts);

        $posts = $this->postsRepository->getPublicArticlesPage($paginator->getOffset(), $paginator->getLength());

        $this->template->posts = $posts;
        $this->template->paginator = $paginator;

        $userId = $this->getUser()->isLoggedIn() ? $this->getUser()->getId() : null;
        $userHasLiked = [];

        if ($userId && count($posts) > 0) {
            // Získáme ID příspěvků na aktuální stránce
            $postIds = array_map(fn($post) => $post->id, $posts->fetchAll());

            // Zjistíme, které příspěvky uživatel označil "like"
            $likes = $this->database->table('likes')
                ->where('user_id', $userId)
                ->where('post_id', $postIds)
                ->fetchPairs('post_id', 'post_id'); // Vrací pole post_id => post_id

            $userHasLiked = $likes;
        }

        $this->template->userHasLiked = $userHasLiked;
    }

    public function actionDelete(int $id): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Přístup zamítnut.', 'error');
            $this->redirect('Sign:in');
        }

        $post = $this->postsRepository->findById($id);
        if (!$post) {
            $this->error('Příspěvek nenalezen.');
        }

        $user = $this->getUser();
        if (!$user->isInRole('admin') && $post->user_id !== $user->getId()) {
            $this->flashMessage('Nemáte oprávnění ke smazání tohoto příspěvku.', 'error');
            $this->redirect('Home:default');
        }

        $this->postsRepository->delete($id);
        $this->flashMessage('Příspěvek byl smazán.', 'success');
        $this->redirect('Home:default');
    }

    public function handleLike(int $postId): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Musíš být přihlášen.', 'error');
            $this->redirect('this');
        }

        $userId = $this->getUser()->getId();
        $likesTable = $this->database->table('likes');
        $like = $likesTable
            ->where('user_id', $userId)
            ->where('post_id', $postId)
            ->fetch();

        $postsTable = $this->database->table('posts');

        if ($like) {
            // Odebrání lajku
            $like->delete();
            $postsTable->where('id', $postId)->update(['likes_count-=' => 1]);
        } else {
            // Přidání lajku
            $likesTable->insert(['user_id' => $userId, 'post_id' => $postId]);
            $postsTable->where('id', $postId)->update(['likes_count+=' => 1]);
        }

        if ($this->isAjax()) {
            $this->redrawControl('likeArea');
        } else {
            $this->redirect('this');
        }
    }

    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');
    }
}
