<?php

namespace App\Presentation\Search;

use App\Model\Search\SearchFacade;
use Nette\Application\UI\Presenter;

final class SearchPresenter extends \App\Presentation\BasePresenter
{
    public function __construct(
        private SearchFacade $searchFacade,
    ) {}

    protected function startup(): void
    {
        parent::startup();
        $this->setLayout('layout');
    }

    public function renderDefault(string $q = ''): void
    {
        $q = trim($q);
        $this->template->q       = $q;
        $this->template->results = $q !== '' ? $this->searchFacade->search($q) : [];
    }
}
