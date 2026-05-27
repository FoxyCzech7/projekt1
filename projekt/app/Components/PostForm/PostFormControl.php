<?php

namespace App\Components\PostForm;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use App\Model\PostFacade;
use App\Model\Premium\PremiumFacade;
use App\Model\Tags\TagFacade;
use Nette\Security\Authorizator;

final class PostFormControl extends Control
{
    public function __construct(
        private PostFacade $postFacade,
        private PremiumFacade $premiumFacade,
        private TagFacade $tagFacade,
        private Authorizator $authorizator,
        private string $uploadsDir,
        private ?int $postId,
    ) {}

    protected function createComponentForm(): Form
    {
        $form = new Form;

        $form->addText('title', 'Nadpis:')
            ->setRequired('Zadejte prosím nadpis.');

        $form->addTextArea('content', 'Obsah:')
            ->setRequired('Zadejte prosím obsah příspěvku.');

        if ($this->canCreatePremiumPost()) {
            $form->addCheckbox('is_premium', 'Prémiový příspěvek')
                ->setOption('description', 'Obsah uvidí pouze prémiové uživatelé.');
        } else {
            $form->addHidden('is_premium', '0');
        }

        $form->addText('tags', 'Tagy:')
            ->setRequired(false)
            ->setOption('description', 'Oddělte čárkou, např.: tanky, drony, letectvo');

        $form->addSelect('status', 'Stav:', [
            'published' => 'Publikováno',
            'draft'     => 'Koncept (draft)',
        ])->setDefaultValue('published');

        $form->addText('scheduled_at', 'Plánované zveřejnění:')
            ->setRequired(false)
            ->setHtmlType('datetime-local')
            ->setOption('description', 'Příspěvek se zobrazí automaticky v nastavený čas. Status se ignoruje — plánované příspěvky jsou vždy published.');

        $form->addUpload('image', 'Úvodní obrázek:')
            ->setRequired(false)
            ->addRule($form::MIME_TYPE, 'Soubor musí být obrázek (JPEG, PNG nebo GIF)', ['image/jpeg', 'image/png', 'image/gif'])
            ->addRule($form::MAX_FILE_SIZE, 'Maximální velikost obrázku je 2 MB', 2 * 1024 * 1024);

        $form->addSubmit('send', 'Uložit příspěvek');
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, \stdClass $data): void
    {
        $presenter = $this->getPresenter();
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }

        if ($this->postId) {
            $post = $this->postFacade->findById($this->postId);
            if (!$post) {
                $presenter->error('Příspěvek nenalezen.');
            }
            if (!$this->isAllowedPostEdit($post)) {
                $presenter->flashMessage('Nemáte oprávnění upravovat tento příspěvek.', 'error');
                $presenter->redirect('Post:show', $this->postId);
            }

            $imagePath = ($data->image instanceof \Nette\Http\FileUpload && $data->image->isOk())
                ? $this->saveImage($data->image, $this->uploadsDir, $this->postId)
                : $post->image;

            $isPremium = $this->canCreatePremiumPost() && $data->is_premium;
            $scheduledAt = !empty($data->scheduled_at) ? new \DateTime($data->scheduled_at) : null;
            $status = ($scheduledAt && $scheduledAt > new \DateTime()) ? 'published' : $data->status;
            $this->postFacade->updatePost(
                $this->postId, $data->title, $data->content, $imagePath, $isPremium,
                $status, $scheduledAt, (int) $presenter->getUser()->getId(),
            );
            $this->tagFacade->syncPostTags($this->postId, $data->tags ?? '');
            $presenter->flashMessage('Příspěvek byl upraven.', 'success');
            $presenter->redirect('Post:show', $this->postId);
        } else {
            if (!$this->isAllowed('post', 'add')) {
                $presenter->flashMessage('Nemáte oprávnění vytvářet příspěvky.', 'error');
                $presenter->redirect('Home:default');
            }
            $isPremium = $this->canCreatePremiumPost() && $data->is_premium;
            $scheduledAt = !empty($data->scheduled_at) ? new \DateTime($data->scheduled_at) : null;
            $status = ($scheduledAt && $scheduledAt > new \DateTime()) ? 'published' : ($data->status ?? 'published');
            $newPost = $this->postFacade->createPost(
                $data->title, $data->content, (int) $presenter->getUser()->getId(),
                null, $isPremium, $status, $scheduledAt,
            );
            if ($data->image instanceof \Nette\Http\FileUpload && $data->image->isOk()) {
                $imagePath = $this->saveImage($data->image, $this->uploadsDir, $newPost->id);
                $this->postFacade->updatePost($newPost->id, $newPost->title, $newPost->content, $imagePath);
            }
            $this->tagFacade->syncPostTags($newPost->id, $data->tags ?? '');
            $presenter->flashMessage('Příspěvek byl ' . ($data->status === 'draft' ? 'uložen jako koncept.' : 'vytvořen.'), 'success');
            $presenter->redirect('Post:show', $newPost->id);
        }
    }

    public function setDefaults(array $defaults): void
    {
        $this['form']->setDefaults($defaults);
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
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename) ?: $filename;
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '-', $normalized) ?? '';
        return substr($sanitized, 0, 100);
    }

    private function isAllowedPostEdit(object $post): bool
    {
        $user = $this->getPresenter()->getUser();
        return $user->isLoggedIn()
            && ($user->isInRole('admin') || ($user->isInRole('author') && $post->user_id === $user->getId()));
    }

    private function canCreatePremiumPost(): bool
    {
        $user = $this->getPresenter()->getUser();
        return $user->isInRole('admin') || $this->premiumFacade->isPremium((int) $user->getId());
    }

    private function isAllowed(string $resource, string $privilege): bool
    {
        $user = $this->getPresenter()->getUser();
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

    public function render(): void
    {
        $this->template->render(__DIR__ . '/PostFormControl.latte');
    }
}
