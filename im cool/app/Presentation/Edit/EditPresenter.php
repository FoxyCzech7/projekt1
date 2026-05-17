<?php
namespace App\Presentation\Edit;

use Nette;
use Nette\Application\UI\Form;
use Nette\Database\Explorer;

final class EditPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private Explorer $database,
    ) {}

    public function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }

    private function getEditableItem(int $id, string $type)
    {
        if ($type === 'post') {
            $item = $this->database->table('posts')->get($id);
        } elseif ($type === 'comment') {
            $item = $this->database->table('comments')->get($id);
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
        $id = $this->getParameter('id');
        $type = $this->getParameter('type');

        if (!$id || !$type) {
            $this->error('Chybí ID nebo typ pro úpravu.');
        }

        $item = $this->getEditableItem($id, $type);

        $data = [
            'content' => $values->content,
            'updated_at' => new \DateTimeImmutable(),
        ];

        if ($type === 'post') {
            $data['title'] = $values->title;
        }

        $item->update($data);

        $this->flashMessage(($type === 'post' ? 'Příspěvek' : 'Komentář') . ' byl úspěšně upraven.', 'success');

        if ($type === 'post') {
            $this->redirect('Post:show', $item->id);
        } else {
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

