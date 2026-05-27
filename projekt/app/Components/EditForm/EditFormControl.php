<?php

namespace App\Components\EditForm;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use App\Model\PostFacade;
use App\Model\Comments\CommentFacade;

final class EditFormControl extends Control
{
    public function __construct(
        private PostFacade $postFacade,
        private CommentFacade $commentFacade,
        private int $id,
        private string $type,
    ) {}

    protected function createComponentForm(): Form
    {
        $form = new Form;

        if ($this->type === 'post') {
            $form->addText('title', 'Titulek:')
                ->setRequired('Titulek je povinný');
        } elseif ($this->type !== 'comment') {
            $this->getPresenter()->error('Nepodporovaný typ úpravy.');
        }

        $form->addTextArea('content', 'Obsah:')
            ->setRequired('Pole obsah je povinné.');
        $form->addSubmit('send', 'Uložit');
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, \stdClass $values): void
    {
        $presenter = $this->getPresenter();
        $item = $this->getEditableItem();

        if ($this->type === 'post') {
            $this->postFacade->updatePostContent($this->id, $values->title, $values->content);
            $presenter->flashMessage('Příspěvek byl úspěšně upraven.', 'success');
            $presenter->redirect('Post:show', $item->id);
        } else {
            $this->commentFacade->updateComment($this->id, $values->content);
            $presenter->flashMessage('Komentář byl úspěšně upraven.', 'success');
            $presenter->redirect('Post:show', $item->post_id);
        }
    }

    public function getEditableItem(): mixed
    {
        $presenter = $this->getPresenter();

        if ($this->type === 'post') {
            $item = $this->postFacade->findById($this->id);
        } elseif ($this->type === 'comment') {
            $item = $this->commentFacade->findById($this->id);
        } else {
            $presenter->error('Neznámý typ.');
        }

        if (!$item) {
            $presenter->error($this->type === 'post' ? 'Příspěvek nenalezen.' : 'Komentář nenalezen.');
        }

        if (!$presenter->getUser()->isInRole('admin') && $item->user_id !== $presenter->getUser()->getId()) {
            $presenter->flashMessage('Nemáte oprávnění upravovat tento ' . ($this->type === 'post' ? 'příspěvek.' : 'komentář.'), 'error');
        }

        return $item;
    }

    public function setDefaults(array $defaults): void
    {
        $this['form']->setDefaults($defaults);
    }

    public function render(): void
    {
        $this->template->render(__DIR__ . '/EditFormControl.latte');
    }
}
