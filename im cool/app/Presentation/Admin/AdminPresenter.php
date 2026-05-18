<?php

namespace App\Presentation\Admin;

use App\Model\Admin\UserProfileFacade;
use Nette\Application\UI\Presenter;

/**
 * Admin sekce — přehled uživatelů a jejich profily.
 * Přístupný pouze pro roli 'admin'.
 */
final class AdminPresenter extends Presenter
{
    public function __construct(
        private UserProfileFacade $userProfileFacade,
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

    /** Seznam všech uživatelů. */
    public function renderDefault(): void
    {
        $this->template->users = $this->userProfileFacade->getAllUsers();
    }

    /**
     * Smaže uživatele i všechna jeho data.
     * Admin nemůže smazat sám sebe.
     */
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

        // Username uložíme před smazáním — PHPStan neví, že error() nikdy nevrátí,
        // takže $profile by zůstal null|object i za if-blokem.
        $username = $profile->username;
        $this->userProfileFacade->deleteUser($id);
        $this->flashMessage("Uživatel \"{$username}\" byl smazán.", 'success');
        $this->redirect('Admin:default');
    }

    /** Profil konkrétního uživatele s grafy aktivity. */
    public function renderProfile(int $id): void
    {
        $profile = $this->userProfileFacade->getUserProfile($id);
        if (!$profile) {
            $this->error('Uživatel nenalezen.');
        }
        $this->template->profile = $profile;

        // Vygenerujeme pevnou osu posledních 12 měsíců, aby graf byl vždy plný
        // i když uživatel v některém měsíci nic nepublikoval.
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = (new \DateTime("first day of -$i months"))->format('Y-m');
        }

        $postsPerMonth = $this->userProfileFacade->getPostsPerMonth($id);
        $commentsPerMonth = $this->userProfileFacade->getCommentsPerMonth($id);

        // JSON pro Chart.js — serializujeme v PHP, aby šablona jen vypsala řetězec.
        $this->template->chartLabels = json_encode($months);
        $this->template->chartPosts = json_encode(array_map(fn($m) => $postsPerMonth[$m] ?? 0, $months));
        $this->template->chartComments = json_encode(array_map(fn($m) => $commentsPerMonth[$m] ?? 0, $months));
    }
}
