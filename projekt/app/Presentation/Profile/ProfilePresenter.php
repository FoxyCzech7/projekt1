<?php

namespace App\Presentation\Profile;

use App\Model\Auth\DuplicateEmailException;
use App\Model\Auth\DuplicateNameException;
use App\Model\Bookmarks\BookmarkFacade;
use App\Model\Premium\PremiumFacade;
use App\Model\Profile\ProfileFacade;
use Nette\Application\UI\Form;
use Nette\Database\Explorer;

// Spravuje dva typy profilu:
//   - renderView() - verejny profil libovolneho uzivatele (nevyzaduje prihlaseni)
//   - renderDefault() - vlastni profil prihlaseneho uzivatele se statistikami a editaci
final class ProfilePresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private ProfileFacade $profileFacade,
        private PremiumFacade $premiumFacade,
        private BookmarkFacade $bookmarkFacade,
        private Explorer $database,
    ) {}

    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');
    }

    // Zobrazi verejny profil jineho uzivatele
    // Pristupne bez prihlaseni - pokud ma uzivatel privatni profil (is_public = 0),
    // sablona zobrazi jen username a zpravu "profil je soukromy"
    public function renderView(int $id): void
    {
        $profile = $this->profileFacade->getPublicProfile($id);
        if (!$profile) {
            $this->error('Uživatel nenalezen.', 404);
        }
        $this->template->profile = $profile;
    }

    // Zobrazi vlastni profil prihlaseneho uzivatele
    // Obsahuje statistiky, zalozky, lajknuty obsah a editacni formular
    // Neprihlaseneho presmeruje na login
    public function renderDefault(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }

        $userId = $this->getUser()->getId();
        // Explorer::table() vrati Selection - lazy dotaz spusteny az pri pristupu k datum
        $row = $this->database->table('users')->get($userId);

        $this->template->userData     = $row;
        $this->template->stats        = $this->profileFacade->getStats($userId);
        $this->template->likedPosts    = $this->profileFacade->getLikedPosts($userId);
        $this->template->likedComments = $this->profileFacade->getLikedComments($userId);
        $this->template->bookmarks     = $this->bookmarkFacade->getUserBookmarks($userId);
        $this->template->isPremium     = $this->premiumFacade->isPremium($userId);

        // predvyplni formular aktualnimi hodnotami uzivatele
        $this['editForm']->setDefaults([
            'first_name' => $row->first_name ?? '',
            'last_name'  => $row->last_name  ?? '',
            'username'   => $row->username,
            'email'      => $row->email ?? '',
            'is_public'  => (bool) ($row->is_public ?? true),
        ]);
    }

    // Formular pro upravu profilu
    // Heslo je volitelne - vyplni se jen pokud ho uzivatel chce zmenit
    // Obsahuje checkbox is_public pro prepinani viditelnosti profilu
    protected function createComponentEditForm(): Form
    {
        $form = new Form;

        $form->addText('first_name', 'Jméno:');
        $form->addText('last_name',  'Příjmení:');

        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Zadejte uživatelské jméno.');

        $form->addEmail('email', 'E-mail:')
            ->setRequired('Zadejte e-mail.')
            ->addRule(Form::EMAIL, 'Neplatný formát e-mailu.');

        $form->addPassword('password', 'Nové heslo:')
            ->setRequired(false)
            ->setOption('description', 'Vyplňte jen pokud chcete změnit heslo.');

        // potvrzeni hesla je povinne pouze pokud bylo vyplneno pole "Nove heslo"
        $form->addPassword('password_confirm', 'Potvrdit heslo:')
            ->setRequired(false)
            ->addConditionOn($form['password'], Form::Filled)
                ->setRequired('Zadejte potvrzení hesla.')
                ->addRule(Form::Equal, 'Hesla se neshodují.', $form['password']);

        // verejny/soukromy profil - ovlivnuje co vidi ostatni uzivatele na Profile:view
        $form->addCheckbox('is_public', 'Veřejný profil (ostatní uvidí moje údaje)');

        $form->addSubmit('send', 'Uložit změny');
        $form->onSuccess[] = [$this, 'editFormSucceeded'];
        return $form;
    }

    // Zpracuje odeslani editacniho formulare
    // Vyjimky DuplicateName/Email jsou chyceny a zobrazeny jako chyby primo v policku formulare
    public function editFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->profileFacade->updateProfile(
                $this->getUser()->getId(),
                $values->username,
                $values->email,
                $values->first_name,
                $values->last_name,
                $values->password,
                $values->is_public,
            );
            $this->flashMessage('Profil byl úspěšně aktualizován.', 'success');
            $this->redirect('this');
        } catch (DuplicateNameException) {
            $form['username']->addError('Toto uživatelské jméno je již použito.');
        } catch (DuplicateEmailException) {
            $form['email']->addError('Tento email je již registrován.');
        }
    }
}
