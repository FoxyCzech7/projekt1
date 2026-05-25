<?php

declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Application\Routers\RouteList;

// Tovarna na router - definuje vsechny URL trasy aplikace.
// Trasy se vyhodnocuji shora dolu; prvni shoda vyhraje.
final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;

		// root "/" → uvodni landing page
		$router->addRoute('', 'Landing:default');

		// explicitni trasa pro blog - bez ni by {link Home:default} generoval prazdnou
		// URL "" (Home+default jsou vychozi hodnoty genericke trasy), coz by zachytila
		// landing trasa vyse a zpusobila nekonecny refresh
		$router->addRoute('blog[/<action>[/<id>]]', 'Home:default');

		// genericka trasa pro vsechny ostatni presentery a akce
		$router->addRoute('<presenter>/<action>[/<id>]', 'Home:default');

		return $router;
	}
}
