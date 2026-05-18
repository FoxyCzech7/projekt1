<?php

namespace App\Presentation\Edit;

use Nette;
use Nette\Application\UI\Form;
use App\Model\PostFacade;
use App\Model\Comments\CommentFacade;

final class EditPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private PostFacade $postFacade,
        private CommentFacade $commentFacade,
    ) {}

    public function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }

    private function getEditableItem(int $id, string $type): mixed
    {
        if ($type === 'post') {
            $item = $this->postFacade->findById($id);
        } elseif ($type === 'comment') {
            $item = $this->commentFacade->findById($id);
        } else {
            $this->error('Neznámý typ.');
        }

        if (!$item) {
            $this->error($type === 'post' ? 'Příspěvek nenalezen.' : 'Komentář nenalezen.');
        }

        if (!$this->getUser()->isInRole('admin') && $item->user_id !== $this->getUser()->getId()) {
            $this->flashMessage('Nemáte oprávnění upravovat tento ' . ($type === 'post' ? 'příspěvek.' : 'komentář.'), 'error');
        }

        return $item;
    }

    protected function createComponentEditForm(): Form
    {
        $form = new Form;
        $type = $this->getParameter('type');

        if ($type === 'post') {
            $form->addText('title', 'Titulek:')
                ->setRequired('Titulek je povinný');
        } elseif ($type !== 'comment') {
            $this->error('Nepodporovaný typ úpravy.');
        }

        $form->addTextArea('content', 'Obsah:')
            ->setRequired('Pole obsah je povinné.');

        $form->addSubmit('send', 'Uložit');
        $form->onSuccess[] = [$this, 'editFormSucceeded'];
        return $form;
    }

    public function editFormSucceeded(Form $form, \stdClass $values): void
    {
        $id = (int) $this->getParameter('id');
        $type = $this->getParameter('type');

        if (!$id || !$type) {
            $this->error('Chybí ID nebo typ pro úpravu.');
        }

        $item = $this->getEditableItem($id, $type);

        if ($type === 'post') {
            $this->postFacade->updatePostContent($id, $values->title, $values->content);
            $this->flashMessage('Příspěvek byl úspěšně upraven.', 'success');
            $this->redirect('Post:show', $item->id);
        } else {
            $this->commentFacade->updateComment($id, $values->content);
            $this->flashMessage('Komentář byl úspěšně upraven.', 'success');
            $this->redirect('Post:show', $item->post_id);
        }
    }

    public function renderEdit(int $id, string $type): void
    {
        if (!$id || !$type) {
            $this->error('Chybí ID nebo typ.');
        }

        $item = $this->getEditableItem($id, $type);

        $defaults = ['content' => $item->content];
        if ($type === 'post') {
            $defaults['title'] = $item->title;
            $this->setView('edit');
        } else {
            $this->setView('commentedit');
        }

        $this->getComponent('editForm')->setDefaults($defaults);
        $this->template->item = $item;
    }
}
