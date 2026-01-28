<?php 
namespace Iem\Controller;

use Iem\Controller\DefaultController;
use Iem\Dispositivo\Dispositivo;
use Iem\Dispositivo\DispositivoRepository;
use \DateTimeImmutable;


class DispositivoController extends Controller{
	private DefaultController $defaultController;
	private DispositivoRepository $dispositivoRepository;

	public function __construct(){
		parent::__construct();
		$this->defaultController = new DefaultController();
		$this->dispositivoRepository = new DispositivoRepository();
	}	

	public function pagRegistrarDisp(): void{
		if (UsuarioController::isLoggedIn()){
			$template = 'dispositivos/registro_dispositivo.html.twig';
			$args = [];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}

	public function pagGestionDispositivos(): void{
		$dispositivos = $this->dispositivoRepository->findAll();

		if (UsuarioController::isLoggedIn()){
			$template = 'dispositivos/gestion_dispositivos.html.twig';
			$args = [
				'dispositivos' => $dispositivos,
			];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}

	public function procesoRegistrarDisp(string $identificador, string $nombre_disp): void{
		if (UsuarioController::isLoggedIn()){
			//obtenieno la fecha de registro
			$fecha = new DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));
			$estado = false;

			$dispositivo = new Dispositivo();
			$dispositivo->setIdentificador($identificador);
			$dispositivo->setNombreDisp($nombre_disp);
			$dispositivo->setEstado($estado);
			$dispositivo->setUltimaConexion($fecha);


			$nuevoDisp = $this->dispositivoRepository->insert($dispositivo);

			print var_dump($nuevoDisp);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}
}

?>
