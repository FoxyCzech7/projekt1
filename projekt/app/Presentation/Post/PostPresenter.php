<?php

namespace App\Presentation\Post;

use Nette\Application\UI\Form;
use App\Components\CommentForm\ICommentFormControlFactory;
use App\Components\CommentForm\CommentFormControl;
use App\Model\Bookmarks\BookmarkFacade;
use App\Model\PostFacade;
use App\Model\Comments\CommentFacade;
use App\Model\Premium\PremiumFacade;
use App\Model\Tags\TagFacade;
use Nette\Security\Authorizator;

final class PostPresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private PostFacade $postFacade,
        private CommentFacade $commentFacade,
        private PremiumFacade $premiumFacade,
        private BookmarkFacade $bookmarkFacade,
        private TagFacade $tagFacade,
        private Authorizator $authorizator,
        private ICommentFormControlFactory $commentFormFactory,
    ) {}

    public function renderDefault(): void
    {
        $posts = $this->postFacade->getPublicArticles();
        $this->template->posts = $posts->fetchAll();

        $userHasLiked = [];
        if ($this->getUser()->isLoggedIn()) {
            $postIds = array_map(fn($p) => $p->id, $this->template->posts);
            $userHasLiked = $this->postFacade->getUserLikedPostIds($this->getUser()->getId(), $postIds);
        }
        $this->template->userHasLiked = $userHasLiked;
    }

    public function renderShow(int $id): void
    {
        $post = $this->postFacade->findById($id);
        if (!$post) {
            $this->error('Příspěvek nebyl nalezen.');
        }
        $this->template->post = $post;
        $this->template->postAuthor = $post->ref('users', 'user_id');
        $this->postFacade->incrementViews($id);
        $this->template->readingTime = PostFacade::readingTime($post->content);
        $this->template->tags = $this->tagFacade->getPostTags($id);

        $user = $this->getUser();
        $this->template->hasFullAccess = $user->isInRole('admin')
            || ($user->isLoggedIn() && $this->premiumFacade->isPremium($user->getId()));
        $this->template->isBookmarked = $user->isLoggedIn()
            && $this->bookmarkFacade->isBookmarked($user->getId(), $id);

        $this->template->comments = $this->commentFacade->getCommentsByPost($id);

        $userHasLiked = [];
        $userHasLikedComments = [];
        if ($this->getUser()->isLoggedIn()) {
            $userId = $this->getUser()->getId();
            // pro post predavame [$id] - pole s jednim prvkem, aby getUserLikedPostIds
            // mohlo pouzit stejnou cestu kodu jako na homepage (kde je jich vice)
            $userHasLiked = $this->postFacade->getUserLikedPostIds($userId, [$id]);
            $userHasLikedComments = $this->commentFacade->getUserLikedCommentIds($userId);
        }
        $this->template->userHasLiked = $userHasLiked;
        $this->template->userHasLikedComments = $userHasLikedComments;
    }

    public function renderList(): void
    {
        $this->template->posts = $this->postFacade->getPublicArticles();
    }

    protected function createComponentCommentForm(): CommentFormControl
    {
        $postId = (int) $this->getParameter('id');
        return $this->commentFormFactory->create($postId);
    }

    public function handleBookmark(int $postId): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
            return;
        }
        $added = $this->bookmarkFacade->toggle($this->getUser()->getId(), $postId);
        $this->flashMessage($added ? 'Příspěvek byl uložen do záložek.' : 'Záložka byla odebrána.');
        $this->redirect('this');
    }

    public function handleLike(int $postId): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Musíš být přihlášen.', 'error');
            $this->redirect('this');
            return;
        }

        try {
            $this->postFacade->toggleLike($postId, $this->getUser()->getId());
        } catch (\Exception $e) {
            $this->flashMessage('Došlo k chybě při lajkování.', 'error');
        }

        if ($this->isAjax()) {
            $this->redrawControl('likeArea');
        } else {
            $this->redirect('this');
        }
    }

    public function handleLikeComment(int $commentId): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Musíš být přihlášen.', 'error');
            $this->redirect('this');
            return;
        }

        try {
            $this->commentFacade->toggleLike($commentId, $this->getUser()->getId());
        } catch (\Exception $e) {
            $this->flashMessage('Došlo k chybě při lajkování komentáře.', 'error');
        }

        if ($this->isAjax()) {
            $this->redrawControl('commentLikesArea-' . $commentId);
        } else {
            $this->redirect('this');
        }
    }
}
