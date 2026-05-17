<?php

namespace App\Presentation\Comment;

use App\Model\Comments\CommentsRepository;
use Nette\Application\UI\Presenter;

final class CommentPresenter extends Presenter
{
    public function __construct(
        private CommentsRepository $commentsRepository,
    ) {}

    public function actionDelete(int $id): void
    {
        $comment = $this->commentsRepository->findById($id);
        if (!$comment) {
            $this->error('Komentář nenalezen.');
        }

        $user = $this->getUser();
        if (!$user->isInRole('admin') && $comment->user_id !== $user->getId()) {
            $this->flashMessage('Nemáte oprávnění smazat tento komentář.', 'error');
            $this->redirect('Post:show', $comment->post_id);
        }

        $this->commentsRepository->delete($id);

        $this->flashMessage('Komentář byl smazán.', 'success');
        $this->redirect('Post:show', $comment->post_id);
    }
}
