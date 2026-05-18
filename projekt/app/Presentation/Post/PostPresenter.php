<?php

namespace App\Presentation\Post;

use Nette;
use Nette\Application\UI\Form;
use App\Model\PostFacade;
use App\Model\Comments\CommentFacade;
use App\Model\Premium\PremiumFacade;
use Nette\Security\Authorizator;

/**
 * Zobrazuje detail příspěvku s komentáři a formulář pro přidání komentáře.
 * Deleguje práci s daty na PostFacade a CommentFacade.
 */
final class PostPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private PostFacade $postFacade,
        private CommentFacade $commentFacade,
        private PremiumFacade $premiumFacade,
        private Authorizator $authorizator,
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

        // Admin a prémiový uživatelé vidí vždy celý obsah.
        $user = $this->getUser();
        $this->template->hasFullAccess = $user->isInRole('admin')
            || ($user->isLoggedIn() && $this->premiumFacade->isPremium($user->getId()));

        // Komentáře jsou pole stdClass objektů (ne ActiveRow) — fasáda je obohacuje
        // o username a email uživatele přes ref(), aby šablona nemusela dělat JOIN ručně.
        $this->template->comments = $this->commentFacade->getCommentsByPost($id);

        $userHasLiked = [];
        $userHasLikedComments = [];
        if ($this->getUser()->isLoggedIn()) {
            $userId = $this->getUser()->getId();
            // Pro post předáváme [$id] — pole s jedním prvkem, aby getUserLikedPostIds
            // mohlo použít stejnou cestu kódu jako na homepage (kde je jich více).
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

    protected function createComponentCommentForm(): Form
    {
        $form = new Form;
        $user = $this->getUser();

        // Přihlášení uživatelé nezadávají jméno ani email — berou se z jejich identity.
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
        // parent_id = 0 znamená kořenový komentář; JS ho nastavuje při kliknutí na "Odpovědět".
        $form->addHidden('parent_id', '0');

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
            // $identity->username funguje jako přístup přes getData()['username']
            // díky magic __get v Nette\Security\Identity.
            $name = $user->getIdentity()->username ?? 'Anonym';
            $email = '';
            $userId = $user->getId();
        } else {
            $name = trim($data->name) ?: 'Anonym';
            $email = $data->email;
            $userId = null;
        }

        // parent_id > 0 = odpověď na existující komentář; 0 nebo prázdné = kořenový.
        $parentId = !empty($data->parent_id) && (int) $data->parent_id > 0
            ? (int) $data->parent_id
            : null;

        try {
            $this->commentFacade->addComment($postId, $userId, $name, $email, trim($data->content), $parentId);
            $this->flashMessage('Komentář byl přidán.', 'success');
        } catch (\Exception $e) {
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

    /**
     * Zkontroluje oprávnění přes Authorizator (RBAC).
     * Iterujeme přes role, protože uživatel jich může mít víc.
     */
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
