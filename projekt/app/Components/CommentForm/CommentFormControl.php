<?php

namespace App\Components\CommentForm;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use App\Model\Comments\CommentFacade;
use Nette\Security\Authorizator;

final class CommentFormControl extends Control
{
    public function __construct(
        private CommentFacade $commentFacade,
        private Authorizator $authorizator,
        private int $postId,
    ) {}

    protected function createComponentForm(): Form
    {
        $form = new Form;
        $user = $this->getPresenter()->getUser();

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

        // parent_id = 0 znamena kořenovy komentar; JS ho nastavi pri kliknuti na "Odpovedet"
        $form->addHidden('parent_id', '0');
        $form->addSubmit('send', 'Přidat komentář');
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, \stdClass $data): void
    {
        $presenter = $this->getPresenter();

        if (!$this->isAllowed('comment', 'add')) {
            $presenter->flashMessage('Nemáte oprávnění přidávat komentáře.', 'error');
            $presenter->redirect('Post:show', $this->postId);
        }

        $user = $presenter->getUser();
        if ($user->isLoggedIn()) {
            $name = $user->getIdentity()->username ?? 'Anonym';
            $email = '';
            $userId = (int) $user->getId();
        } else {
            $name = trim($data->name) ?: 'Anonym';
            $email = $data->email;
            $userId = null;
        }

        // parent_id > 0 = odpoved na existujici komentar; 0 nebo prazdne = kořenovy
        $parentId = !empty($data->parent_id) && (int) $data->parent_id > 0
            ? (int) $data->parent_id
            : null;

        try {
            $this->commentFacade->addComment($this->postId, $userId, $name, $email, trim($data->content), $parentId);
            $presenter->flashMessage('Komentář byl přidán.', 'success');
        } catch (\Exception $e) {
            $presenter->flashMessage('Při ukládání komentáře došlo k chybě. Zkuste to prosím znovu.', 'error');
        }

        if ($presenter->isAjax()) {
            $presenter->redrawControl('commentsArea');
        } else {
            $presenter->redirect('Post:show', $this->postId);
        }
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
        $presenter = $this->getPresenter();
        $this->template->isLoggedIn = $presenter->getUser()->isLoggedIn();
        $this->template->signInUrl = $presenter->link('Sign:in');
        $this->template->signRegisterUrl = $presenter->link('Sign:register');
        $this->template->render(__DIR__ . '/CommentFormControl.latte');
    }
}
