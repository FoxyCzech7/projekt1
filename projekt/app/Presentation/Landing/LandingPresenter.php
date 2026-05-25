<?php

declare(strict_types=1);

namespace App\Presentation\Landing;

use Nette\Application\UI\Presenter;


final class LandingPresenter extends Presenter
{
    protected function startup(): void
    {
        parent::startup();
        // vypne standardni @layout.latte - landing page ma vlastni kompletni HTML strukturu
        $this->setLayout(false);
    }

    public function renderDefault(): void
    {
        // sablona podle toho zobrazi bud Prihlasit/Registrovat nebo Muj profil
        $this->template->isLoggedIn = $this->getUser()->isLoggedIn();
    }
}
