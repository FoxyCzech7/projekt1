<?php

namespace App\Presentation\PostForm;

use App\Components\PostForm\IPostFormControlFactory;
use App\Components\PostForm\PostFormControl;
use App\Model\PostFacade;
use App\Model\Tags\TagFacade;
use Nette\Security\Authorizator;

// Formular pro vytvoreni a editaci prispevku
// Pristup povolen jen prihlasenym autorum a adminům
final class PostFormPresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private PostFacade $postFacade,
        private TagFacade $tagFacade,
        private Authorizator $authorizator,
        private IPostFormControlFactory $postFormFactory,
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
        $userId = $this->getUser()->getId();
        $this->template->drafts    = $this->postFacade->getUserDrafts($userId);
        $this->template->scheduled = $this->postFacade->getUserScheduled($userId);
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

    protected function createComponentPostForm(): PostFormControl
    {
        $postId = (int) $this->getParameter('id') ?: null;
        return $this->postFormFactory->create($postId);
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
