<?php
namespace Iem;

use Iem\Controller\DefaultController;
use Iem\Controller\AmbienteController;
//use Iem\Controller\UsuarioController;
use Iem\Controller\DispositivoController;

class Application{
	private DefaultController $defaultController;
	private AmbienteController $ambienteController;
//	private UsuarioController $usuarioController;
	private DispositivoController $dispositivoController;

	public function __construct(){
		$this->defaultController = new DefaultController();
		$this->ambienteController = new AmbienteController();
//		$this->usuarioController = new UsuarioController();
		$this->dispositivoController = new DispositivoController();
	}

	public function run(): void{
		$action = filter_input(INPUT_GET, 'action');
		$isPostSubmission = ($_SERVER['REQUEST_METHOD'] === 'POST');

		switch ($action){
			case 'ambientes':
				$this->defaultController->pagAmbientes();
				break;

			case 'acerca_de':
				$this->defaultController->pagAcerca();
				break;
//			//registro y control de usuarios
//			case 'registrar':
//				$this->usuarioController->pagRegistrar();
//				break;	
//
//			case 'gestion_usuarios':
//				$this->usuarioController->pagGestionUsuarios();
//				break;	
//
//			case 'procesoRegistrar':
//				$nombre = filter_input(INPUT_POST, 'nombre');
//				$apellido = filter_input(INPUT_POST, 'apellido');
//				$username = filter_input(INPUT_POST, 'username');
//				$password = filter_input(INPUT_POST, 'password');
//	
//				if ($isPostSubmission && !empty($nombre) && !empty($apellido) && !empty($username) && !empty($password)){
//					$this->usuarioController->procesoRegistrar($nombre, $apellido, $username, $password);
//				}
//				else {
//					$this->usuarioController->errorRegistro('¡Debe llenar todos los campos de manera obligatoria!');
//				}
//				break;
//
//			case 'procesoLogin':
//				$username = filter_input(INPUT_POST, 'username');
//				$password = filter_input(INPUT_POST, 'password');
//				if (empty($username) && empty($password)){
//					$this->usuarioController->mensajesError('Debe introducir el nombre de usuario y la contraseña');
//					break;
//				}
//				if (empty($username)){
//					$this->usuarioController->mensajesError('Debe introducir el nombre de usuario');
//					break;
//				}
//				if (empty($password)){
//					$this->usuarioController->mensajesError('Debe introducir la contraseña');
//					break;
//				}
//				else {
//					$this->usuarioController->procesoLogin($username, $password);
//				}
//				break;
//
//			//para salir de la sesion
//			case 'logout':
//				$this->usuarioController->logout();
//				break;
//
//			//para borrar usuario
//			case 'borrar_usuario':
//				$id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
//				if($isPostSubmission && !empty($id)){
//					$this->usuarioController->borrarUsuario($id);
//				}
//				break;
//
//			case 'editar_estado':
//				$id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
//				$estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_NUMBER_INT);
//
//				if($isPostSubmission && !empty($id)){
//					$this->usuarioController->editarEstado($id, $estado);
//				}
//				break;
//		

			//desde aqui, el control para los ambientes
			case 'ambiente1':
				$this->ambienteController->pagAmbiente1();
				break;

			case 'ambiente2':
				$this->ambienteController->pagAmbiente2();
				break;

			case 'ambiente3':
				$this->ambienteController->pagAmbiente3();
				break;

			//paginas para los reportes de cada ambiente
			case 'reportes_amb1':
				$this->ambienteController->pagReportes1();
				break;

			case 'reportes_amb2':
				$this->ambienteController->pagReportes2();
				break;

			case 'reportes_amb3':
				$this->ambienteController->pagReportes3();
				break;

			//la consulta de datos ultimos mediante el websocket
			case 'consulta_datos':
				$this->ambienteController->ultimosDatos();
				break;

		//	//recepcion de datos para el ambiente 1
		//	case 'datos_amb1':
		//		//validando metodo y datos recibidos
		//		if ($isPostSubmission){
		//			$this->ambiente1Controller->registrarDatos();
		//		}
		//		else {
		//			http_response_code(405);
		//			echo json_encode(['error' => 'metodo no permitido']);
		//			return;
		//		}
		//		break;

		//	//recepcion de datos para el ambiente 2
				
			//registro y control de dispositivos
			case 'registro_dispositivo':
				$this->dispositivoController->pagRegistrarDisp();
				break;

			case 'gestion_dispositivos':
				$this->dispositivoController->pagGestionDispositivos();
				break;

			case 'procesoRegistrarDisp':
				$identificador = filter_input(INPUT_POST, 'identificador');
				$nombre_disp = filter_input(INPUT_POST, 'nombre_disp');

				if ($isPostSubmission){
					$this->dispositivoController->procesoRegistrarDisp($identificador, $nombre_disp);
				}	
				break;


			default:
				$this->defaultController->pagAmbientes();
				break;
		}

	}
}

?>
