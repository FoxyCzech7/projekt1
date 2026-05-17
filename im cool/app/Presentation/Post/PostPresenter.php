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
        private Explorer $database
    ) {}
    public function renderDefault(): void
    {
        $this->template->posts = $this->database->table('posts')->order('created_at DESC')->fetchAll();

        $userId = $this->getUser()->getId();
        $this->template->userHasLiked = [];

        if ($userId) {
            // Přímé získání post_id, kde uživatel lajknul, pak vytvoření rychlé mapy
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
            $commentsData[] = (object)[
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
            // Najednou získáme lajky na komentáře a posty
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

    public function renderCreate(): void
    {
        if (!$this->isAllowed('post', 'add')) {
            $this->flashMessage('Nemáte oprávnění vytvářet příspěvky.', 'error');
            $this->redirect('Post:list');
        }
    }
    public function renderEdit(int $id): void
    {
        $post = $this->postsRepository->findById($id);
        if (!$post) {
            $this->error('Příspěvek nenalezen');
        }
        if (!$this->isAllowedPostEdit($post)) {
            $this->flashMessage('Nemáte oprávnění upravovat tento příspěvek.', 'error');
            $this->redirect('Post:show', $id);
        }

        $this['postForm']->setDefaults([
            'title' => $post->title,
            'content' => $post->content,
        ]);
        $this->template->post = $post;
        $this->template->setFile(__DIR__ . '/../edit/edit.latte');
    }
    protected function createComponentPostForm(): Form
    {
        $form = new Form;

        $form->addText('title', 'Nadpis:')
            ->setRequired('Zadejte prosím nadpis.');

        $form->addTextArea('content', 'Obsah:')
            ->setRequired('Zadejte prosím obsah příspěvku.');

        $form->addUpload('image', 'Úvodní obrázek:')
            ->setRequired(false)
            ->addRule($form::MIME_TYPE, 'Soubor musí být obrázek (JPEG, PNG nebo GIF)', ['image/jpeg', 'image/png', 'image/gif'])
            ->addRule($form::MAX_FILE_SIZE, 'Maximální velikost obrázku je 2 MB', 2 * 1024 * 1024);

        $form->addSubmit('send', 'Uložit příspěvek');
        $form->onSuccess[] = [$this, 'postFormSucceeded'];
        return $form;
    }
    private function sanitizeFileName(string $filename): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '-', $normalized);
        return substr($sanitized, 0, 100);
    }
    public function postFormSucceeded(Form $form, \stdClass $data): void
    {
        $id = (int)$this->getParameter('id');
        $uploadsDir = __DIR__ . '/../../../www/img/posts/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        $imagePath = null;
        if ($id) {
            // Editace příspěvku
            $post = $this->postsRepository->findById($id);
            if (!$post) {
                $this->error('Příspěvek nenalezen.');
            }
            if (!$this->isAllowedPostEdit($post)) {
                $this->flashMessage('Nemáte oprávnění upravovat tento příspěvek.', 'error');
                $this->redirect('Post:show', $id);
            }

            if ($data->image instanceof \Nette\Http\FileUpload && $data->image->isOk()) {
                $imagePath = $this->saveImage($data->image, $uploadsDir, $id);
            } else {
                $imagePath = $post->image;
            }
            $this->postsRepository->updatePost($id, $data->title, $data->content, $imagePath);
            $this->flashMessage('Příspěvek byl upraven.', 'success');
            $this->redirect('Post:show', $id);

        } else {
            if (!$this->isAllowed('post', 'add')) {
                $this->flashMessage('Nemáte oprávnění vytvářet příspěvky.', 'error');
                $this->redirect('Post:list');
            }
            $newPost = $this->postsRepository->createPost(
                $data->title,
                $data->content,
                $this->getUser()->getId(),
                null
            );
            if ($data->image instanceof \Nette\Http\FileUpload && $data->image->isOk()) {
                $imagePath = $this->saveImage($data->image, $uploadsDir, $newPost->id);
                $this->postsRepository->updatePost($newPost->id, $newPost->title, $newPost->content, $imagePath);
            }
            $this->flashMessage('Příspěvek byl vytvořen.', 'success');
            $this->redirect('Post:show', $newPost->id);
        }
    }
    private function saveImage(\Nette\Http\FileUpload $fileUpload, string $uploadsDir, int $postId): string
    {
        $sanitizedName = pathinfo($this->sanitizeFileName($fileUpload->getName()), PATHINFO_FILENAME);
        $extension = pathinfo($fileUpload->getName(), PATHINFO_EXTENSION);
        $finalName = $sanitizedName . '-' . $postId . '.' . $extension;
        $fileUpload->move($uploadsDir . $finalName);
        return 'img/posts/' . $finalName;
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
        $this->redrawControl('commentsArea'); // přidej redraw oblasti komentářů
    } else {
        $this->redirect('Post:show', $postId);
    }
}
public function actionDeleteComment(int $id): void
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
    if ($this->isAjax()) {
        $this->redrawControl('commentsArea');
    } else {
        $this->redirect('Post:show', $comment->post_id);
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
private function isAllowedPostEdit(object $post): bool
{
    $user = $this->getUser();
    return $user->isLoggedIn()
        && ($user->isInRole('admin') || ($user->isInRole('author') && $post->user_id === $user->getId()));
}

private function isAllowed(string $resource, string $privilege): bool
{
    $user = $this->getUser();
    if (!$user->isLoggedIn()) {
        return false;
    }

    foreach ((array)$user->getRoles() as $role) {
        if ($this->authorizator->isAllowed($role, $resource, $privilege)) {
            return true;
        }
    }

    return false;
}
}