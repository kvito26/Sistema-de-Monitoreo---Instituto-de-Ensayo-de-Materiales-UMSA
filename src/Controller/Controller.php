<?php
namespace Iem\Controller;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Twig\TwigFunction;

abstract class Controller{
	const PATH_TO_TEMPLATE = __DIR__ . '/../../templates';

	protected Environment $twig;

	public function __construct(){
		$loader = new FilesystemLoader(self::PATH_TO_TEMPLATE);
		$this->twig = new Environment($loader);
		$this->twig->addFunction(
			new TwigFunction('isLoggedIn', [UsuarioController::class, 'isLoggedIn'])
		);

	}
}

?>
