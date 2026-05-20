<?php

declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Application\Routers\RouteList;

/**
 * Továrna na router — definuje všechny URL trasy aplikace.
 * Trasy se vyhodnocují shora dolů; první shoda vyhraje.
 */
final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;

		// Root "/" → úvodní landing page s videem a tlačítky
		$router->addRoute('', 'Landing:default');

		// Explicitní trasa pro blog — bez ní by {link Home:default} generoval prázdnou
		// URL "" (Home+default jsou výchozí hodnoty generické trasy), což by zachytila
		// landing trasa výše a způsobila nekonečný refresh.
		$router->addRoute('blog[/<action>[/<id>]]', 'Home:default');

		// Generická trasa pro všechny ostatní presentery a akce
		$router->addRoute('<presenter>/<action>[/<id>]', 'Home:default');

		return $router;
	}
}
