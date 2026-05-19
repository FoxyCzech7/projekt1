<?php

declare(strict_types=1);

namespace App\Presentation\Landing;

use Nette\Application\UI\Presenter;

final class LandingPresenter extends Presenter
{
    protected function startup(): void
    {
        parent::startup();
        $this->setLayout(false);
    }

    public function renderDefault(): void
    {
        $this->template->isLoggedIn = $this->getUser()->isLoggedIn();
    }
}
