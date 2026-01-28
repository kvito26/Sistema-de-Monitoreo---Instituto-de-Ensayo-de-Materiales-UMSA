<?php
namespace Iem\Controller;

use Iem\Controller\DefaultController;
use Iem\Ambiente\AmbienteRepository;

class AmbienteController extends Controller{
	private DefaultController $defaultController;
	private AmbienteRepository $ambienteRepository;

	public function __construct(){
		parent::__construct();
		$this->defaultController = new DefaultController();
		$this->ambienteRepository = new AmbienteRepository();
	}

	public function pagAmbiente1(): void{
		if (UsuarioController::isLoggedIn()){
			$template = 'ambientes/ambiente_1.html.twig';
			$args = [];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}

	public function pagAmbiente2(): void{
		if (UsuarioController::isLoggedIn()){
			$template = 'ambientes/ambiente_2.html.twig';
			$args = [];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}
	
	public function pagAmbiente3(): void{
		if (UsuarioController::isLoggedIn()){
			$template = 'ambientes/ambiente_3.html.twig';
			$args = [];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}

	//funciones para los reportes de cada ambiente
	public function pagReportes1(): void{
		$ambientes = $this->ambienteRepository->findAll();

		if (UsuarioController::isLoggedIn()){
			$template = 'reportes/reportes_amb1.html.twig';
			$args = [
				'ambientes' => $ambientes,
			];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}

	public function pagReportes2(): void{
		if (UsuarioController::isLoggedIn()){
			$template = 'reportes/reportes_amb2.html.twig';
			$args = [];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}
	
	public function pagReportes3(): void{
		if (UsuarioController::isLoggedIn()){
			$template = 'reportes/reportes_amb3.html.twig';
			$args = [];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}
	//funcion para obtener el ultimo dato de temp y hum del dispositivo seleccionado 
	public function ultimosDatos(): void{
		//la salida de la funcion es json
		header('Content-Type: application/json');

		$id_disp = filter_input(INPUT_GET, 'id_disp');

		//comprovando si existe el dispositivo
		if (!$id_disp){
			echo json_encode(['error' => 'no se tiene el dispositivo en la consulta']);
			return;
		}

		$ultima_lectura = $this->ambienteRepository->findLastRowByDevice($id_disp);

		if ($ultima_lectura){
			echo json_encode([
				'estado' => 'datos_recuperados',
				'temp' => $ultima_lectura->getTemp(),
				'hum' => $ultima_lectura->getHum(),
				'fecha' => $ultima_lectura->getFecha()->format('d/m H:i:s'),
			]);
		}
		else{
			echo json_encode([
				'estado' => 'vacio',
				'temp' => 0, 
				'hum' => 0,
			]);
		}
		
		exit;
	}
}



?>
