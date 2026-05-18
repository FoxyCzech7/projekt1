<?php

namespace App\Presentation\Comment;

use App\Model\Comments\CommentFacade;
use Nette\Application\UI\Presenter;

final class CommentPresenter extends Presenter
{
    public function __construct(
        private CommentFacade $commentFacade,
    ) {}

    public function actionDelete(int $id): void
    {
        $comment = $this->commentFacade->findById($id);
        if (!$comment) {
            $this->error('Komentář nenalezen.');
        }

        $user = $this->getUser();
        if (!$user->isInRole('admin') && $comment->user_id !== $user->getId()) {
            $this->flashMessage('Nemáte oprávnění smazat tento komentář.', 'error');
            $this->redirect('Post:show', $comment->post_id);
        }

        $postId = $comment->post_id;
        $this->commentFacade->deleteComment($id);

        $this->flashMessage('Komentář byl smazán.', 'success');
        $this->redirect('Post:show', $postId);
    }
}
