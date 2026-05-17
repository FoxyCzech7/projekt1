<?php

namespace App\Presentation\PostForm;

use Nette;
use Nette\Application\UI\Form;
use App\Model\Posts\PostsRepository;
use Nette\Security\Authorizator;

final class PostFormPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private PostsRepository $postsRepository,
        private Authorizator $authorizator,
    ) {}

    protected function startup(): void
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro tuto akci musíte být přihlášeni.', 'error');
            $this->redirect('Sign:in');
        }
    }

    public function renderCreate(): void
    {
        if (!$this->isAllowed('post', 'add')) {
            $this->flashMessage('Nemáte oprávnění vytvářet příspěvky.', 'error');
            $this->redirect('Home:default');
        }
    }

    public function renderEdit(int $id): void
    {
        $post = $this->postsRepository->findById($id);
        if (!$post) {
            $this->error('Příspěvek nenalezen.');
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

    public function postFormSucceeded(Form $form, \stdClass $data): void
    {
        $id = (int) $this->getParameter('id');
        $uploadsDir = __DIR__ . '/../../../www/img/posts/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        if ($id) {
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
                $this->redirect('Home:default');
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

    private function sanitizeFileName(string $filename): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '-', $normalized);
        return substr($sanitized, 0, 100);
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

        foreach ((array) $user->getRoles() as $role) {
            if ($this->authorizator->isAllowed($role, $resource, $privilege)) {
                return true;
            }
        }

        return false;
    }
}
