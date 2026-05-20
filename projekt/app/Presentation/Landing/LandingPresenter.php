<?php

declare(strict_types=1);

namespace App\Presentation\Landing;

use Nette\Application\UI\Presenter;

/**
 * Úvodní stránka (landing page) s videem na pozadí.
 * Nezobrazuje standardní navigaci ani patičku — má vlastní samostatný HTML dokument.
 * Přístupná bez přihlášení.
 */
final class LandingPresenter extends Presenter
{
    protected function startup(): void
    {
        parent::startup();
        // Vypne standardní @layout.latte — landing page má vlastní kompletní HTML strukturu
        $this->setLayout(false);
    }

    public function renderDefault(): void
    {
        // Šablona podle toho zobrazí buď "Přihlásit/Registrovat" nebo "Můj profil"
        $this->template->isLoggedIn = $this->getUser()->isLoggedIn();
    }
}
