<?php
namespace Iem\Controller;

class DefaultController extends Controller{
	public function pagAmbientes(): void{
		if (UsuarioController::isLoggedIn()){
			$template = 'ambientes.html.twig';
			$args = [];

			print $this->twig->render($template, $args);
		}
		else {
			self::pagLogin();
		}
	}

	public function pagLogin(): void{
		$template = 'login.html.twig';
		$args = [];

		print $this->twig->render($template, $args);
	}

	public function pagAcerca(): void{
		$template = 'acerca_de.html.twig';
		$args = [];

		print $this->twig->render($template, $args);

	}

}


?>
