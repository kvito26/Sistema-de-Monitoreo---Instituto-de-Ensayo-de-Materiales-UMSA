<?php
namespace Iem\Controller;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Twig\TwigFunction;

abstract class Controller{
	const PATH_TO_TEMPLATE = __DIR__ . '/../../templates';
	const PATH_TO_MANIFEST = __DIR__ . '/../../public/build/.vite/manifest.json'; //para saber cual es el cambio de nombre hash

	protected Environment $twig;

	public function __construct(){
		$loader = new FilesystemLoader(self::PATH_TO_TEMPLATE);
		$this->twig = new Environment($loader);
//		$this->twig->addFunction(
//			new TwigFunction('isLoggedIn', [UsuarioController::class, 'isLoggedIn'])
//		);
		
		//agregar la funcion para twig y obtener la ruta
		$this->twig->addFunction(
			new TwigFunction('getCompiledPath',	function ($entrada){
				return $this->getCompiledPath($entrada);
			}));

	}

	//leer el archivo manifest.json y retornar la ruta del archivo compilado
	private function getCompiledPath($entrada): string{
		if (!file_exists(self::PATH_TO_MANIFEST)){
			return '/build/' . $entrada;
		}

		$manifest = json_decode(file_get_contents(self::PATH_TO_MANIFEST), true);
		if (isset($manifest[$entrada])){
			return '/build/' . $manifest[$entrada]["file"];
		}

		return '/build/' . $entrada;
	}
}

?>
