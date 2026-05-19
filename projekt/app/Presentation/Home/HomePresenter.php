<?php

namespace App\Presentation\Home;

use App\Model\PostFacade;
use App\Model\Premium\PremiumFacade;
use Nette\Application\UI\Presenter;
use Nette\Utils\Paginator;

/**
 * Zobrazuje seznam příspěvků s stránkováním.
 * Deleguje veškerou práci s daty na PostFacade — sám neví nic o DB.
 */
final class HomePresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private PostFacade $postFacade,
        private PremiumFacade $premiumFacade,
    ) {
        parent::__construct();
    }

    public function renderDefault(int $page = 1): void
    {
        $itemsPerPage = 5;

        $paginator = new Paginator();
        $paginator->setItemsPerPage($itemsPerPage);
        $paginator->setPage($page);
        $paginator->setItemCount($this->postFacade->getPublicArticlesCount());

        // Selection je lazy — data se načtou až při fetchAll() nebo iteraci v šabloně.
        $posts = $this->postFacade->getPublicArticlesPage($paginator->getOffset(), $paginator->getLength());

        $this->template->posts = $posts;
        $this->template->paginator = $paginator;

        $userHasLiked = [];
        if ($this->getUser()->isLoggedIn()) {
            // fetchAll() musí proběhnout před getUserLikedPostIds(), aby byl Selection
            // již hydratovaný — jinak by se dotaz spustil dvakrát.
            $postIds = array_map(fn($post) => $post->id, $posts->fetchAll());
            if ($postIds) {
                $userHasLiked = $this->postFacade->getUserLikedPostIds($this->getUser()->getId(), $postIds);
            }
        }
        $this->template->userHasLiked = $userHasLiked;

        $user = $this->getUser();
        $this->template->hasFullAccess = $user->isInRole('admin')
            || ($user->isLoggedIn() && $this->premiumFacade->isPremium($user->getId()));
    }

    public function actionDelete(int $id): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Přístup zamítnut.', 'error');
            $this->redirect('Sign:in');
        }

        $post = $this->postFacade->findById($id);
        if (!$post) {
            $this->error('Příspěvek nenalezen.');
        }

        $user = $this->getUser();
        if (!$user->isInRole('admin') && $post->user_id !== $user->getId()) {
            $this->flashMessage('Nemáte oprávnění ke smazání tohoto příspěvku.', 'error');
            $this->redirect('Home:default');
        }

        $this->postFacade->deletePost($id);
        $this->flashMessage('Příspěvek byl smazán.', 'success');
        $this->redirect('Home:default');
    }

    public function handleLike(int $postId): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Musíš být přihlášen.', 'error');
            $this->redirect('this');
            return;
        }

        $this->postFacade->toggleLike($postId, $this->getUser()->getId());

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
