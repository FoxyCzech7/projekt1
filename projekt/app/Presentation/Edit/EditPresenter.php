<?php

namespace App\Presentation\Edit;

use App\Components\EditForm\IEditFormControlFactory;
use App\Components\EditForm\EditFormControl;

final class EditPresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private IEditFormControlFactory $editFormFactory,
    ) {}

    public function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }

    protected function createComponentEditForm(): EditFormControl
    {
        return $this->editFormFactory->create(
            (int) $this->getParameter('id'),
            (string) $this->getParameter('type'),
        );
    }

    public function renderEdit(int $id, string $type): void
    {
        $component = $this['editForm'];
        $item = $component->getEditableItem();

        $defaults = ['content' => $item->content];
        if ($type === 'post') {
            $defaults['title'] = $item->title;
            $this->setView('edit');
        } else {
            $this->setView('commentedit');
        }

        $component->setDefaults($defaults);
        $this->template->item = $item;
    }
}
