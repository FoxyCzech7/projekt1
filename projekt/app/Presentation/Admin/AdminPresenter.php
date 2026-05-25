<?php

namespace App\Presentation\Admin;

use App\Model\Admin\UserProfileFacade;
use App\Model\Feed\FeedImportFacade;
use Nette\Application\UI\Presenter;

// Admin sekce - prehled uzivatelu a jejich profily
// Pristupny pouze pro roli 'admin'
final class AdminPresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private UserProfileFacade $userProfileFacade,
        private FeedImportFacade $feedImportFacade,
    ) {}

    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');

        if (!$this->getUser()->isLoggedIn() || !$this->getUser()->isInRole('admin')) {
            $this->flashMessage('Přístup pouze pro administrátory.', 'error');
            $this->redirect('Home:default');
        }
    }

    // Stahne nejnovejsi polozku z RSS feedu a vytvori z ni prispevek
    // Autorem je prihlaseny admin. Cache feedu se po importu vymaze
    public function actionImportFeed(): void
    {
        $uploadsDir = __DIR__ . '/../../../www/img/posts/';

        try {
            $result = $this->feedImportFacade->importLatestPost($this->getUser()->getId(), $uploadsDir);
            if ($result === 'created') {
                $this->flashMessage('Příspěvek byl úspěšně importován z feedu.', 'success');
            } else {
                $this->flashMessage('Příspěvek s tímto názvem již existuje — import přeskočen.', 'info');
            }
        } catch (\RuntimeException $e) {
            $this->flashMessage('Chyba při načítání feedu: ' . $e->getMessage(), 'error');
        }

        $this->redirect('Admin:default');
    }

    // Seznam vsech uzivatelu.
    public function renderDefault(): void
    {
        $this->template->users = $this->userProfileFacade->getAllUsers();
    }

    // Smaze uzivatele i vsechna jeho data
    // Admin nemuze smazat sam sebe
    public function actionDelete(int $id): void
    {
        if ($id === $this->getUser()->getId()) {
            $this->flashMessage('Nemůžete smazat vlastní účet.', 'error');
            $this->redirect('Admin:profile', $id);
        }

        $profile = $this->userProfileFacade->getUserProfile($id);
        if (!$profile) {
            $this->error('Uživatel nenalezen.');
        }

        // username ulozime pred smazanim - error() nikdy nevrati, $profile by zustavalo null za if-blokem
        $username = $profile->username;
        $this->userProfileFacade->deleteUser($id);
        $this->flashMessage("Uživatel \"{$username}\" byl smazán.", 'success');
        $this->redirect('Admin:default');
    }

    // Profil konkretniho uzivatele s grafy aktivity
    public function renderProfile(int $id): void
    {
        $profile = $this->userProfileFacade->getUserProfile($id);
        if (!$profile) {
            $this->error('Uživatel nenalezen.');
        }
        $this->template->profile = $profile;

        // pevna osa poslednich 12 mesicu, aby graf byl vzdy plny i kdyz uzivatel v nekterem mesici nic nepublikoval
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = (new \DateTime("first day of -$i months"))->format('Y-m');
        }

        $postsPerMonth = $this->userProfileFacade->getPostsPerMonth($id);
        $commentsPerMonth = $this->userProfileFacade->getCommentsPerMonth($id);

        // JSON pro Chart.js - serializujeme v PHP, aby sablona jen vypsala retezec
        $this->template->chartLabels = json_encode($months);
        $this->template->chartPosts = json_encode(array_map(fn($m) => $postsPerMonth[$m] ?? 0, $months));
        $this->template->chartComments = json_encode(array_map(fn($m) => $commentsPerMonth[$m] ?? 0, $months));
    }
}
