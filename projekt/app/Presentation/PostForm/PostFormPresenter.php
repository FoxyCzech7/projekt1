<?php

namespace App\Presentation\PostForm;

use Nette;
use Nette\Application\UI\Form;
use App\Model\PostFacade;
use App\Model\Premium\PremiumFacade;
use App\Model\Tags\TagFacade;
use Nette\Security\Authorizator;

/**
 * Formulář pro vytvoření a editaci příspěvku.
 * Přístup povolen jen přihlášeným autorům a adminům.
 */
final class PostFormPresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private PostFacade $postFacade,
        private PremiumFacade $premiumFacade,
        private TagFacade $tagFacade,
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

    public function renderDrafts(): void
    {
        $this->template->drafts = $this->postFacade->getUserDrafts($this->getUser()->getId());
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
        $post = $this->postFacade->findById($id);
        if (!$post) {
            $this->error('Příspěvek nenalezen.');
        }
        if (!$this->isAllowedPostEdit($post)) {
            $this->flashMessage('Nemáte oprávnění upravovat tento příspěvek.', 'error');
            $this->redirect('Post:show', $id);
        }

        $scheduledAt = isset($post->scheduled_at) && $post->scheduled_at
            ? (new \DateTime($post->scheduled_at))->format('Y-m-d\TH:i')
            : '';

        $this['postForm']->setDefaults([
            'title'        => $post->title,
            'content'      => $post->content,
            'is_premium'   => (bool) ($post->is_premium ?? false),
            'tags'         => $this->tagFacade->getPostTagString($id),
            'status'       => $post->status ?? 'published',
            'scheduled_at' => $scheduledAt,
        ]);
        $this->template->post      = $post;
        $this->template->revisions = $this->postFacade->getRevisions($id);
    }

    protected function createComponentPostForm(): Form
    {
        $form = new Form;

        $form->addText('title', 'Nadpis:')
            ->setRequired('Zadejte prosím nadpis.');

        $form->addTextArea('content', 'Obsah:')
            ->setRequired('Zadejte prosím obsah příspěvku.');

        // Checkbox zobrazíme jen uživatelům, kteří sami mají aktivní premium nebo jsou admin.
        // Server-side guard v postFormSucceeded() pak ignoruje is_premium=true od ostatních,
        // takže podvržený POST request taky nezabere.
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
            ->setOption('description', 'Ponechte prázdné pro okamžité zveřejnění');

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
            // Editace existujícího příspěvku
            $post = $this->postFacade->findById($id);
            if (!$post) {
                $this->error('Příspěvek nenalezen.');
            }
            if (!$this->isAllowedPostEdit($post)) {
                $this->flashMessage('Nemáte oprávnění upravovat tento příspěvek.', 'error');
                $this->redirect('Post:show', $id);
            }

            // Pokud uživatel nenahrál nový obrázek, ponecháme stávající cestu.
            $imagePath = ($data->image instanceof \Nette\Http\FileUpload && $data->image->isOk())
                ? $this->saveImage($data->image, $uploadsDir, $id)
                : $post->image;

            // Server-side guard: is_premium smí být true jen pro prémiové/admin uživatele.
            $isPremium   = $this->canCreatePremiumPost() && $data->is_premium;
            $scheduledAt = !empty($data->scheduled_at) ? new \DateTime($data->scheduled_at) : null;
            $this->postFacade->updatePost(
                $id, $data->title, $data->content, $imagePath, $isPremium,
                $data->status, $scheduledAt, $this->getUser()->getId(),
            );
            $this->tagFacade->syncPostTags($id, $data->tags ?? '');
            $this->flashMessage('Příspěvek byl upraven.', 'success');
            $this->redirect('Post:show', $id);
        } else {
            // Vytvoření nového příspěvku
            if (!$this->isAllowed('post', 'add')) {
                $this->flashMessage('Nemáte oprávnění vytvářet příspěvky.', 'error');
                $this->redirect('Home:default');
            }
            // Post se vytvoří nejdřív bez obrázku, aby existovalo jeho ID
            // pro pojmenování souboru (image-{postId}.jpg).
            $isPremium   = $this->canCreatePremiumPost() && $data->is_premium;
            $scheduledAt = !empty($data->scheduled_at) ? new \DateTime($data->scheduled_at) : null;
            $newPost = $this->postFacade->createPost(
                $data->title, $data->content, $this->getUser()->getId(),
                null, $isPremium, $data->status ?? 'published', $scheduledAt,
            );
            if ($data->image instanceof \Nette\Http\FileUpload && $data->image->isOk()) {
                $imagePath = $this->saveImage($data->image, $uploadsDir, $newPost->id);
                $this->postFacade->updatePost($newPost->id, $newPost->title, $newPost->content, $imagePath);
            }
            $this->tagFacade->syncPostTags($newPost->id, $data->tags ?? '');
            $this->flashMessage('Příspěvek byl ' . ($data->status === 'draft' ? 'uložen jako koncept.' : 'vytvořen.'), 'success');
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

    /**
     * Převede diakritiku a odstraní nebezpečné znaky z názvu souboru.
     * Bez tohoto kroku by útočník mohl nahrát soubor se jménem jako "../../config.php".
     */
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

    /**
     * Prémiový příspěvek smí vytvořit jen ten, kdo má sám aktivní premium nebo je admin.
     * Admin má přístup vždy; ostatní musí mít zakoupené předplatné.
     */
    private function canCreatePremiumPost(): bool
    {
        $user = $this->getUser();
        return $user->isInRole('admin')
            || $this->premiumFacade->isPremium($user->getId());
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
