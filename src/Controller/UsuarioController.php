<?php
namespace Iem\Controller;

use Iem\Usuario\Usuario;
use Iem\Usuario\UsuarioRepository;
use Iem\Controller\DefaultController;

class UsuarioController extends Controller{
	private UsuarioRepository $usuarioRepository;
	private DefaultController $defaultController;

	public function __construct(){
		parent::__construct();
		$this->usuarioRepository = new UsuarioRepository();
		$this->defaultController = new DefaultController();
	}

	public function pagRegistrar(): void{
		if (self::isLoggedIn()){
			$template = 'usuarios/registro.html.twig';
			$args = [];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}

	public function pagGestionUsuarios(): void{
		//obteniendo usuarios registrados
		$usuarios = $this->usuarioRepository->findAll();
		if (self::isLoggedIn()){
			$template = 'usuarios/gestion_usuarios.html.twig';
			$args = [
				'usuarios' => $usuarios,
			];

			print $this->twig->render($template, $args);
		}
		else {
			$this->defaultController->pagLogin();
		}
	}

	public function procesoRegistrar(string $nombre, string $apellido, string $username, string $password){
		$usuario = new Usuario();	

		$usuario->setNombre($nombre);
		$usuario->setApellido($apellido);
		$usuario->setUsername($username);
		$usuario->setPassword($password);
		//estado del usario, por defecto false en la creacion
		$usuario->setEstado(false);


		$nuevoUsuarioId = $this->usuarioRepository->insert($usuario);

		print var_dump($nuevoUsuarioId);
	}

	public function borrarUsuario(int $id): void{
		$this->usuarioRepository->delete($id);

		$location = '/umsa-iem/public/?action=gestion_usuarios';
		header("Location: $location");
	}

	public function editarEstado(int $id, bool $estado): void{
		$usuario_estado = $this->usuarioRepository->find($id);

		$usuario_estado->setEstado($estado);
		$this->usuarioRepository->update($usuario_estado);

		$location = '/umsa-iem/public/?action=gestion_usuarios';
		header("Location: $location");
	}

	public function errorRegistro(string $mensaje): void{
		$template = 'errores/error_registro.html.twig';
		$args = [
			'mensaje' => $mensaje,
		];

		print $this->twig->render($template, $args);
	}

	//proceso del login de usuario
	public function procesoLogin(string $username, string $password): void{
		$loginSuccess = $this->isValidUsernamePassword($username, $password);

		if ($loginSuccess){
			$_SESSION['username'] = $username;
			$this->defaultController->pagAmbientes();
		}
		else {
			$this->mensajesError('¡ERROR! - Reingresar nombre de usuario y contraseña');
		}
		
	}

	//autenticacion
	public function isValidUsernamePassword(string $username, string $password): bool{
		$user = $this->usuarioRepository->findOneByUsername($username);

		if($user == NULL){
			return false;
		}

		return password_verify($password, $user->getPassword());
	}

	//funciton para verificar la autenticidad de usuario para la navegacion
	public static function isLoggedIn(): bool{
		if (isset($_SESSION['username'])){
			return true;
		}
		else {
			return false;
		}
	}

	//funcion de errores de ingreso
	public function mensajesError(string $mensaje): void{
		$template = 'errores/login_error.html.twig';
		$args = [
			'mensaje' => $mensaje,
		];

		print $this->twig->render($template, $args);
	}

	//salir de la sesion
	public function logout(): void{
		$_SESSION = [];
		// 2. Destruir la cookie de sesión en el navegador
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // 3. Destruir la sesión en el servidor
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // 4. Redirigir
    $this->defaultController->pagLogin();
    exit; // Importante para detener la ejecución
	}


}



?>
