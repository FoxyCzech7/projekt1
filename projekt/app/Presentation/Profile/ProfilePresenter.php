<?php

namespace App\Presentation\Profile;

use App\Components\ProfileEditForm\IProfileEditFormControlFactory;
use App\Components\ProfileEditForm\ProfileEditFormControl;
use App\Model\Bookmarks\BookmarkFacade;
use App\Model\Premium\PremiumFacade;
use App\Model\Profile\ProfileFacade;
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
        private IProfileEditFormControlFactory $editFormFactory,
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

    protected function createComponentEditForm(): ProfileEditFormControl
    {
        return $this->editFormFactory->create();
    }
}
