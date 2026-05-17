<?php

namespace App\Presentation\Post;

use Nette;
use Nette\Application\UI\Form;
use App\Model\Posts\PostsRepository;
use App\Model\Comments\CommentsRepository;
use Nette\Security\Authorizator;
use Nette\Database\Explorer;

final class PostPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private PostsRepository $postsRepository,
        private CommentsRepository $commentsRepository,
        private Authorizator $authorizator,
        private Explorer $database,
    ) {}

    public function renderDefault(): void
    {
        $this->template->posts = $this->database->table('posts')->order('created_at DESC')->fetchAll();

        $userId = $this->getUser()->getId();
        $this->template->userHasLiked = [];

        if ($userId) {
            $likedPostIds = $this->database->table('likes')
                ->where('user_id', $userId)
                ->fetchPairs(null, 'post_id');
            $this->template->userHasLiked = array_fill_keys($likedPostIds, true);
        }
    }

    public function renderShow(int $id): void
    {
        $post = $this->postsRepository->findById($id);
        if (!$post) {
            $this->error('Příspěvek nebyl nalezen.');
        }
        $this->template->post = $post;

        $comments = $this->database->table('comments')
            ->where('post_id', $id)
            ->order('created_at DESC')
            ->fetchAll();

        $commentsData = [];
        foreach ($comments as $comment) {
            $user = $comment->ref('users', 'user_id');
            $commentsData[] = (object) [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
                'user_id' => $comment->user_id,
                'username' => $user?->username,
                'email' => $user?->email,
                'likes_count' => $comment->likes_count,
            ];
        }
        $this->template->comments = $commentsData;

        $userId = $this->getUser()->getId();
        $this->template->userHasLikedComments = [];
        $this->template->userHasLiked = [];

        if ($userId) {
            $likedCommentIds = $this->database->table('comment_likes')
                ->where('user_id', $userId)
                ->fetchPairs(null, 'comment_id');
            $this->template->userHasLikedComments = array_fill_keys($likedCommentIds, true);

            $likedPostIds = $this->database->table('likes')
                ->where('user_id', $userId)
                ->fetchPairs(null, 'post_id');
            $this->template->userHasLiked = array_fill_keys($likedPostIds, true);
        }
    }

    public function renderList(): void
    {
        $this->template->posts = $this->postsRepository->findAll();
    }

    protected function createComponentCommentForm(): Form
    {
        $form = new Form;
        $user = $this->getUser();

        if ($user->isLoggedIn()) {
            $form->addTextArea('content', 'Komentář:')
                ->setRequired('Zadejte prosím obsah komentáře.')
                ->addRule(Form::MIN_LENGTH, 'Komentář musí mít alespoň %d znaků.', 5);
        } else {
            $form->addText('name', 'Autor:')
                ->setRequired('Zadejte prosím vaše jméno.')
                ->addRule(Form::MIN_LENGTH, 'Jméno musí mít alespoň %d znaků.', 2);

            $form->addEmail('email', 'E-mail:')
                ->setRequired('Zadejte prosím váš e-mail.');

            $form->addTextArea('content', 'Komentář:')
                ->setRequired('Zadejte prosím obsah komentáře.')
                ->addRule(Form::MIN_LENGTH, 'Komentář musí mít alespoň %d znaků.', 5);
        }
        $form->addSubmit('send', 'Přidat komentář');
        $form->onSuccess[] = [$this, 'commentFormSucceeded'];
        return $form;
    }

    public function commentFormSucceeded(Form $form, \stdClass $data): void
    {
        $postId = (int) $this->getParameter('id');
        if (!$postId) {
            $this->error('Neznámé ID příspěvku.');
        }
        if (!$this->isAllowed('comment', 'add')) {
            $this->flashMessage('Nemáte oprávnění přidávat komentáře.', 'error');
            $this->redirect('Post:show', $postId);
        }

        $user = $this->getUser();
        if ($user->isLoggedIn()) {
            $identity = $user->getIdentity();
            $name = $identity->name ?? 'Anonym';
            $email = $identity->email ?? '';
            $userId = $user->getId();
        } else {
            $name = trim($data->name) ?: 'Anonym';
            $email = $data->email;
            $userId = null;
        }

        $this->database->beginTransaction();
        try {
            $this->commentsRepository->insert([
                'post_id' => $postId,
                'name' => $name,
                'email' => $email,
                'content' => trim($data->content),
                'created_at' => new \DateTimeImmutable(),
                'user_id' => $userId,
            ]);
            $this->database->commit();
            $this->flashMessage('Komentář byl přidán.', 'success');
        } catch (\Exception $e) {
            $this->database->rollBack();
            $this->flashMessage('Při ukládání komentáře došlo k chybě. Zkuste to prosím znovu.', 'error');
        }

        if ($this->isAjax()) {
            $this->redrawControl('commentsArea');
        } else {
            $this->redirect('Post:show', $postId);
        }
    }

    public function handleLike(int $postId): void
    {
        $user = $this->getUser();
        if (!$user->isLoggedIn()) {
            $this->flashMessage('Musíš být přihlášen.', 'error');
            $this->redirect('this');
        }

        $userId = $user->getId();

        $this->database->beginTransaction();
        try {
            $like = $this->database->table('likes')
                ->where('user_id', $userId)
                ->where('post_id', $postId)
                ->fetch();

            if ($like) {
                $like->delete();
                $this->database->table('posts')->where('id', $postId)->update([
                    'likes_count' => new \Nette\Database\SqlLiteral('likes_count - 1'),
                ]);
            } else {
                $this->database->table('likes')->insert([
                    'user_id' => $userId,
                    'post_id' => $postId,
                ]);
                $this->database->table('posts')->where('id', $postId)->update([
                    'likes_count' => new \Nette\Database\SqlLiteral('likes_count + 1'),
                ]);
            }
            $this->database->commit();
        } catch (\Exception $e) {
            $this->database->rollBack();
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
        $user = $this->getUser();
        if (!$user->isLoggedIn()) {
            $this->flashMessage('Musíš být přihlášen.', 'error');
            $this->redirect('this');
        }

        $userId = $user->getId();

        $this->database->beginTransaction();
        try {
            $like = $this->database->table('comment_likes')
                ->where('user_id', $userId)
                ->where('comment_id', $commentId)
                ->fetch();

            if ($like) {
                $like->delete();
                $this->database->table('comments')
                    ->where('id', $commentId)
                    ->update(['likes_count' => new \Nette\Database\SqlLiteral('likes_count - 1')]);
            } else {
                $this->database->table('comment_likes')->insert([
                    'user_id' => $userId,
                    'comment_id' => $commentId,
                ]);
                $this->database->table('comments')
                    ->where('id', $commentId)
                    ->update(['likes_count' => new \Nette\Database\SqlLiteral('likes_count + 1')]);
            }
            $this->database->commit();
        } catch (\Exception $e) {
            $this->database->rollBack();
            $this->flashMessage('Došlo k chybě při lajkování komentáře.', 'error');
        }

        if ($this->isAjax()) {
            $this->redrawControl('commentLikesArea-' . $commentId);
        } else {
            $this->redirect('this');
        }
    }

    private function isAllowed(string $resource, string $privilege): bool
    {
        $user = $this->getUser();
        if (!$user->isLoggedIn()) {
            return false;
        }

        foreach ((array) $user->getRoles() as $role) {
            if ($this->authorizator->isAllowed($role, $resource, $privilege)) {
                return true;
            }
        }

        return false;
    }
}
