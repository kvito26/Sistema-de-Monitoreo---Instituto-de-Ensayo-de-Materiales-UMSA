<?php
//codigo para implementar el servidor websocket
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php';

use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use Swoole\Table;
use Swoole\Http\Request;
use Swoole\Timer;
use Swoole\Server\Task;
use Iem\WebSocket\WSocketController;
use Iem\OrmHelper;
use Iem\Dispositivo\DispositivoRepository;

OrmHelper::setEntityManager($entityManager);
date_default_timezone_set('America/La_Paz');	

//creando una tabla en memoria compartida para guardar dispositivos autenticados
$dispAutenticados = new Table(100);
$dispAutenticados->column('fd', Table::TYPE_INT);
$dispAutenticados->column('hora_conexion_cont', Table::TYPE_INT);
$dispAutenticados->create();

//creando una tabla en memoria compartida para guardar los clientes web
$clientesWeb = new Table(2048);
$clientesWeb->column('fd', Table::TYPE_INT);
$clientesWeb->column('hora_conexion_web', Table::TYPE_INT);
$clientesWeb->create();

//creando los objetos servidor y controller del mismo. objeto del repositorio de los dispositivos
$server = new Server("0.0.0.0", 8080, SWOOLE_PROCESS);
$controller = new WSocketController();

//**************************************************************************************
//funciones auxiliares
function mensajesLog(string $nivel, string $mensaje, array $contexto = []): void{
	$timestamp = date('Y-m-d H:i:s');

	//colores ANSI para terminal	
	$colores = [
		'INFO' => "\033[36m", //cyan
		'EXITOSO' => "\033[32m", //verde
		'ADVERTENCIA' => "\033[33m", //amarillo
		'ERROR' => "\033[31m", //rojo
		'DEBUG' => "\033[35m", //magenta
		'RESET' => "\033[0m", //no color
		'CRITICO' => "\033[1;31m", //rojo brillante
		'CONECTADO' => "\033[32m", //verde
		'DESCONECTADO' => "\033[90m", //gris
		'AUTENTICADO' => "\033[92m", //cyan
		'DATOS' => "\033[34m", //azul
		'COMANDO' => "\033[33m", //amarillo
		'FEEDBACK' => "\033[35m", //magenta
		'TAREA' => "\033[36m", //cyan
		'HEARTHBEAT' => "\033[38;5;208m", //naranja
		'ELIMINADO' => "\033[38;5;88m",
	];

	$color = $colores[$nivel] ?? $colores['RESET'];
	$reset = $colores['RESET'];

	//convirtiendo a json
	$contexto_json = !empty($contexto) ? ' | ' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

	$logLine = "[{$timestamp}] [{$color}{$nivel}{$reset}] {$mensaje}{$contexto_json}" . PHP_EOL;

	echo $logLine;
}

//function para la emision de feedbacks
function emisionFeedbacks(Server $server, Table $clientesWeb, array $payload): void{
	$feedPayload = json_encode($payload);
	mensajesLog('INFO', "La cantidad de clientes web para la emision", ['cantidad_clientes_web' => $clientesWeb->count()]);

	foreach($clientesWeb as $fd => $datos_cliente){
		$fd_web = $datos_cliente['fd'];
		//echo "intentando con el cliente: " . $fd_web . PHP_EOL;
		try{
		//	echo "dentro del try\n";
		//	echo "si existe: " . var_dump($server->isEstablished($fd_web)) . PHP_EOL;
			if ($server->isEstablished($fd_web)){
				$server->push($fd_web, $feedPayload);
				mensajesLog('EXITOSO', "Se ha enviado el feedback", ['cliente_web' => $fd_web]);
			}
		}
		catch(\Exception $e){
			mensajesLog('ERROR', "Error al emitir feedback al cliente web", ['error_feed_fd' => $fd_web]);
		}
	}
}

//funcion para la emision de datos TH a los clientes web
function emisionDatosTH(Server $server, Table $clientesWeb, array $payload): void{
	$thPayload =json_encode($payload);
	mensajesLog('INFO', "La cantidad de clientes web para la emision de datos TH", ['cantidad_clientes_web' => $clientesWeb->count()]);

	foreach($clientesWeb as $fd => $datos_cliente){
		$fd_web = $datos_cliente['fd'];

		try{
			if ($server->isEstablished($fd_web)){
				$server->push($fd_web, $thPayload);
				mensajesLog('EXITOSO', "Se ha enviado los datos TH", ['cliente_web' => $fd_web]);
			}
		}
		catch(\Exception $e){
			mensajesLog('ERROR', "Error al emitir datos TH al cliente web", ['error_TH_fd' => $e->getMessage()]);
		}
	}
}

//funcion para el envio de comandos hacia los dispositivos 
function envioComando(Server $server, Table $dispAutenticados, array $payload, int $fd_disp): void{
	$comandoPayload = json_encode($payload);
	mensajesLog('INFO', "La cantidad de clientes autenticados", ['cantidad_autenticados' => $dispAutenticados->count()]);
	
	try{
		if ($server->isEstablished($fd_disp)){
			$server->push($fd_disp, $comandoPayload);
			mensajesLog('EXITOSO', "Se ha enviado el comando al controlador", ['fd_disp' => $fd_disp]);
		}	
	}	
	catch(\Exception $e){
		mensajesLog('ERROR', "Error al enviar el comando al controlador", ['error_comando' => $e->getMessage()]); 
	}
}

//funcion para la emision de desconexion de un controlador a los clientes web
function emisionDesconexion(Server $server, Table $clientesWeb, array $payload): void{
	$payloadDesconex = json_encode($payload);
	mensajesLog('INFO', "La cantidad de clientes web para la emision de desconexion", ['cantidad_clientes_web' => $clientesWeb->count()]);

	foreach($clientesWeb as $fd => $datos_cliente){
		$fd_web = $datos_cliente['fd'];

		try{
			if ($server->isEstablished($fd_web)){
				$server->push($fd_web, $payloadDesconex);
				mensajesLog('EXITOSO', "Se ha enviado el estado de desconexion", ['cliente_web' => $fd_web]);
			}
		}
		catch(\Exception $e){
			mensajesLog('ERROR', "Error al emitir el estado de desconexion", ['error' => $e->getMessage()]);
		}
	}
}
//**************************************************************************************
//evento start (solo una ejecucion)
$server->on("start", function(Server $server){
	echo str_repeat("=", 60) . PHP_EOL;
	echo "Servidor WebSocket IEM desplegado en el puerto 8080" . PHP_EOL;
	echo "Procesos en el Sistema Operativo:" . PHP_EOL;
	echo "\tMaster PID: " . $server->master_pid . PHP_EOL;
	echo "\tManager PID: " . $server->manager_pid . PHP_EOL;
	echo "Para detener el servidor <Ctrl+C>" . PHP_EOL;
	echo str_repeat("=", 60) . PHP_EOL;
});

//evento open (se ejecuta cada vez que haya un nuevo cliente)
$server->on("open", function(Server $server, Request $request) use ($clientesWeb){
	//obteniendo la direccion ip del nuevo dispositivo
	$ip = $request->server['remote_addr'] ?? 'desconocida';

	mensajesLog('CONECTADO', "Nueva Conexion", [
		'fd' => $request->fd,
		'ip' => $ip,
	]);

	//mensaje de bienvenida al nuevo dispositivo
	$mensajeBienvenida = json_encode([
		"estado" => "conectado",
		"mensaje" => "Te has conectado al servidor WebSocket del IEM-UMSA, si eres un desconocido, desconectate. El evento fue reportado.",
		"timestamp" => time(),
		"fd" => $request->fd,
	]);

	//asumiendo en primera instancia que es un cliente web
	$clientesWeb->set($request->fd, ['fd' => $request->fd, 'hora_conexion_web' => time()]);
	mensajesLog('INFO', "Conexion guardada por defecto en clientes web", ['fd' => $request->fd]);


	//enviando mensaje al cliente
	try{
		$server->push($request->fd, $mensajeBienvenida);
	}
	catch(Exception $e){
		mensajesLog('ERROR', "Error al enviar el mensaje de bienvenida", ['error' => $e->getMenssage()]);
	}
});

//evento tarea (task) gestionadado por task workers
$server->on('task', function(Server $server, Task $task) use ($clientesWeb, $dispAutenticados){
	//saber que task worker esta realizando la tarea:
	echo "\nTask worker: " . $server->worker_id . " esta procesando la tarea" . PHP_EOL;
	
	$datosTarea = $task->data;
	$tipoTarea = $datosTarea['tipo'] ?? 'desconocido';

//	echo "clientes web dentro de task: " . PHP_EOL;
//	foreach($clientesWeb as $fd => $cliente){
//		$fd_web = $cliente['fd'];
//		echo "cliente web task: (fd)" . $fd_web . PHP_EOL;
//	}
//
//	echo "\nclientes conectados totales: " . PHP_EOL;
//	foreach($server->connections as $fd){
//		echo "\t cliente en la conexion task (fd): " . $fd . PHP_EOL;
//	}

	switch ($tipoTarea){
		case 'emision_feedbacks':
			emisionFeedbacks($server, $clientesWeb, $datosTarea['payload']);
			break;	
		
		case 'emision_datosTH':
			emisionDatosTH($server, $clientesWeb, $datosTarea['payload']);
			break;

		case 'emision_desconex':
			emisionDesconexion($server, $clientesWeb, $datosTarea['payload']);
			break;

		case 'envio_comando':
			envioComando($server, $dispAutenticados, $datosTarea['payload'], $datosTarea['fd']);
			break;

		default:
			mensajesLog('ERROR', "Error en el tipo de tarea a ejecutar");
			break;
	}
});

//evento al finalizar la tarea
$server->on('finish', function(Server $server, $task_id, $result){
	mensajesLog('EXITOSO', "La tarea se ha completado con exito", ['exito_tarea' => $task_id]);	
	print_r($result);
});




//evento message (logica de funcionamiento del servidor), recibir datos json
$server->on("message", function (Server $server, Frame $frame) use ($dispAutenticados, $clientesWeb, $controller){
	//recuperando el mensaje y decodificando json
	$datos_recibidos = json_decode($frame->data, true);

	//validando el json
	if (!$datos_recibidos){
		mensajesLog('ADVERTENCIA', "Se ha recibido un mensaje JSON invalido", ['fd' => $frame->fd]);
		return;
	}
		
	//validando si hay 'action' dentro del json
	if(!isset($datos_recibidos['action'])){
		mensajesLog('ADVERTENCIA', "Se ha recibido un mensaje sin el campo 'action'", ['fd' => $frame->fd]);
		return;
	}

	$action = $datos_recibidos['action'];

	switch ($action){
		//caso para autenticacion
		case 'autenticar':
			//proceso de autenticacion y actualizacion de clientes web
			if ($clientesWeb->exists($frame->fd)){
				//contanto los clientes web y los dispositivos autenticados
				echo "el numero de clientes web: " . $clientesWeb->count() . PHP_EOL;
				echo "el numero de disp autenti: " . $dispAutenticados->count() . PHP_EOL;

				//borrando de la tabla de clientes web y agregando a la tabla de registrados (autenticados)
				echo "es el cliente web: " . $clientesWeb->get($frame->fd, 'fd') . PHP_EOL;
				$clientesWeb->del($frame->fd);
				mensajesLog('INFO', "Cliente borrado de lista webs", ['fd' => $frame->fd]);
			}

			$dispAutenticados->set($datos_recibidos['id_disp'], ['fd' => $frame->fd]);

			//log de autenticado y guardado en la tabla
			mensajesLog("AUTENTICADO", "Se ha autenticado y guardado un nuevo dispositivo", ['dispositivo' => $datos_recibidos['id_disp']]);

			echo "el numero de clientes web: " . $clientesWeb->count() . PHP_EOL;
			echo "el numero de disp autenti: " . $dispAutenticados->count() . PHP_EOL;

			break;


		//caso para recibir datos de temp y hum
		case 'datos_TH':
			try{
				//registrando los datos en la DB
				$controller->registroTH($datos_recibidos);

				//enviando los datos recogidos en la web, para que lo recoja js
			//	$payloadWeb = json_encode([
			//		'action' => 'datos_actualizados',
			//		'id_disp' => $datos_recibidos['id_disp'],
			//		'temp' => $datos_recibidos['temp'],
			//		'hum' => $datos_recibidos['hum'],
			//		'fecha' => date('d/m H:i:s'),
			//	]);

				$payloadWeb = [
					'action' => 'datos_actualizados',
					'id_disp' => $datos_recibidos['id_disp'],
					'temp' => $datos_recibidos['temp'],
					'hum' => $datos_recibidos['hum'],
					'fecha' => date('d/m H:i:s'),
				];

				//reenviando a todos los clientes web
				//imprimiendo todos los clitentes web
				echo "\tClientes web listado: " . PHP_EOL;
				foreach($clientesWeb as $fd_web => $datos){
					echo "\t\tCliente web:" .  $datos['fd'] . " " . PHP_EOL;
				}

				//llamar a todos los clientes web
				echo "***************************************************\n";
				echo "\tNumero clientes web (int): " . $clientesWeb->count() . PHP_EOL;
				echo "***************************************************\n";

				$server->task([
					'tipo' => 'emision_datosTH',
					'payload' => $payloadWeb,
				]);

				mensajesLog("DATOS", "Datos registrados y emitidos", [
					'ambiente' => $datos_recibidos['id_disp'],
					'temp' => $datos_recibidos['temp'],
					'hum' => $datos_recibidos['hum'],
					'timestamp' => date('d/m H:i:s'),
				]);

			//	//emison de datos a todos los clientes web (solo funciona con un solo worker)
			//	foreach($clientesWeb as $fd => $datos){
			//		$fd_web = (int) $datos['fd'];
			//		if($server->isEstablished($fd_web)){
			//			echo "**********estrellitas********" . PHP_EOL;
			//			echo "fd web: " . $fd_web . PHP_EOL;
			//			try{
			//				$server->push($fd_web, $payloadWeb);
			//				mensajesLog('INFO', "Datos TH emitido al cliente web", ['cliente_receptor' => $fd_web]);
			//			}
			//			catch(\Exception $e){
			//				mensajesLog('ERROR', "Error al hacer el feedback TH al cliente web", ['error_feedback' => $fd_web]);
			//			}
			//		}
			//	}
			}
			catch(\Exception $e){
				mensajesLog("ERROR", "Error al registrar y reenviar todos los datos", ['error' => $e->getMessage()]);	
			}
			break;

		//caso para enviar comandos al microcontrolador
		case 'comando':
			if(!$datos_recibidos['id_objetivo']){
				return;	
			}

			$disp_objetivo = $dispAutenticados->get($datos_recibidos['id_objetivo']);

			$tipo_comando = $datos_recibidos['tipo'];

			switch ($tipo_comando){
				case 'ordenVent': case 'ordenCalent': case 'ordenHumi': 
					try{
						$comando_task = [
							'tipo' => $datos_recibidos['tipo'],
							'valor' => $datos_recibidos['valor'],
						];

						$server->task([
							'tipo' => 'envio_comando',
							'fd' => $disp_objetivo['fd'],
							'payload' => $comando_task,
						]);

						//verificando la existencia del dispositivo objetivo (realizando con un solo worker, tambien funciona por el momento con varios task workers)
					//	if ($disp_objetivo && $server->isEstablished($disp_objetivo['fd'])){
					//		mensajesLog('INFO', "El dispositivo esta autenticado", [
					//			'dispositivo' => $datos_recibidos['id_objetivo'],
					//			'tipo_comando' => $datos_recibidos['tipo'],
					//		]);
					//		
					//		//enviando la orden al dispositivo
					//		$mensaje = json_encode([
					//			'tipo' => $datos_recibidos["tipo"],
					//			'valor' => $datos_recibidos["valor"],
					//		]);

					//		$server->push($disp_objetivo['fd'], $mensaje);
					//		mensajesLog('COMANDO', "Se ha enviado el comando", [
					//			'dispositivo' => $datos_recibidos['id_objetivo'],
					//			'tipo_comando' => $datos_recibidos['tipo'],
					//			'valor' => $datos_recibidos["valor"],
					//		]);
					//	}

						mensajesLog('COMANDO', "Se ha enviado el comando", [
							'dispositivo' => $datos_recibidos['id_objetivo'],
							'tipo_comando' => $datos_recibidos['tipo'],
							'valor' => $datos_recibidos["valor"],
						]);
					}
					catch(\Exception $e){
						mensajesLog('ERROR', "No se ha podido enviar el comando", ['error_comando' => [
							'dispositivo' => $datos_recibidos['id_objetivo'],
							'tipo_comando' => $datos_recibidos['tipo'],
						]]);
					}
					break;

				case 'ordenVent_Var':
					$valor_pwm = (int) (255 * $datos_recibidos['valor'] / 100);
					try{
						$comando_task = [
							'tipo' => $datos_recibidos['tipo'],
							'valor' => $valor_pwm,
						];

						$server->task([
							'tipo' => 'envio_comando',
							'fd' => $disp_objetivo['fd'],
							'payload' => $comando_task,
						]);

						mensajesLog('COMANDO', "Se ha enviado el comando", [
							'dispositivo' => $datos_recibidos['id_objetivo'],
							'tipo_comando' => $datos_recibidos['tipo'],
							'valor_pwm' => $valor_pwm,
						]);
					}
					catch(\Exception $e){
						mensajesLog('ERROR', "No se ha podido enviar el comando", ['error_comando' => [
							'dispositivo' => $datos_recibidos['id_objetivo'],
							'tipo_comando' => $datos_recibidos['tipo'],
						]]);
					}
					break;

				case 'automatico':
					try{
						$comando_task = [
							'tipo' => $datos_recibidos['tipo'],
							'temp' => $datos_recibidos['temp'],
							'hum' => $datos_recibidos['hum'],
						];

						$server->task([
							'tipo' => 'envio_comando',
							'fd' => $disp_objetivo['fd'],
							'payload' => $comando_task,
						]);
						
						mensajesLog('COMANDO', "Se ha recibido la orden para el sistema automatico", [
							'dispositivo' => $datos_recibidos['id_objetivo'],
							'tipo_comando' => $datos_recibidos['tipo'],
							'temp' => $datos_recibidos['temp'],
							'hum' => $datos_recibidos['hum'],
						]);	
					}
					catch(\Exception $e){
						mensajesLog('ERROR', "No se ha podido enviar el comando", ['error_comando' => [
							'dispositivo' => $datos_recibidos['id_objetivo'],
							'tipo_comando' => $datos_recibidos['tipo'],
						]]);
					}
					break;

				case 'detener_todo':
					try{
						$comando_task = [
							'tipo' => $datos_recibidos['tipo'],
						];

						$server->task([
							'tipo' => 'envio_comando',
							'fd' => $disp_objetivo['fd'],
							'payload' => $comando_task,
						]);
						
						mensajesLog('COMANDO', "Se ha recibido la orden para el sistema automatico", [
							'dispositivo' => $datos_recibidos['id_objetivo'],
							'tipo_comando' => $datos_recibidos['tipo'],
						]);	
					}
					catch(\Exception $e){
						mensajesLog('ERROR', "No se ha podido enviar el comando", ['error_comando' => [
							'dispositivo' => $datos_recibidos['id_objetivo'],
							'tipo_comando' => $datos_recibidos['tipo'],
						]]);
					}
					break;

				default:
					mensajesLog('ADVERTENCIA', "Tipo de comando desconocido", ['tipo_comando_desconocido (fd_emisor)' => $frame->fd]);
					break;
			}
			break;

		//caso para el feedback enviado por el dispositivo
		case 'feedbackVent': case 'feedbackCalent': case 'feedbackHumi': case 'feedbackNivel':
			try{
				$controller->procesoFeedback($datos_recibidos);

				//imprimiendo todos los clitentes web
				echo "\tClientes web listado: " . PHP_EOL;
				foreach($clientesWeb as $fd_web => $datos){
					echo "\t\tCliente web (fd): " . $datos['fd'] . " " . PHP_EOL;
				}
				//llamar a todos los clientes web
				echo "***************************************************\n";
				echo "\tNumero clientes web (int): " . $clientesWeb->count() . PHP_EOL;
				echo "***************************************************\n";

				$server->task([
					'tipo' => 'emision_feedbacks',
					'payload' => $datos_recibidos,
				]);


			//	//emision de feedback a todos los clientes web (solo funciona con un solo worker)
			//	foreach($clientesWeb as $fd => $datos){
			//		$fd_web = (int) $datos['fd'];
			//		if($server->isEstablished($fd_web)){
			//			echo "**********estrellitas********" . PHP_EOL;
			//			echo "fd web: " . $fd_web . PHP_EOL;
			//			try{
			//				$server->push($fd_web, json_encode($datos_recibidos));
			//				mensajesLog('INFO', "Feedback emitido al cliente web", ['cliente_receptor' => $fd_web]);
			//			}
			//			catch(\Exception $e){
			//				mensajesLog('ERROR', "Error al hacer el feedback al cliente web", ['error_feedback' => $fd_web]);
			//			}
			//		}
			//	}

				mensajesLog('FEEDBACK', "Feedback hecho desde un dispositivo registrado", ['feedback' => $datos_recibidos]);
			}
			catch(\Exception $e){
				mensajesLog('ERROR', "No se pudo registrar ni difundir los feedbacks", ['error' => $e->getMessage()]);	
			}
			break;

		default:
			mensajesLog('ERROR', "No se obtuvo una llave 'action' conocida", ['error_action' => 'No se tiene un action conocido']);
			break;

		case 'heartbeat':
			mensajesLog('HEARTHBEAT', "El dispositivo esta vivo", [
				'heartbeat' => $frame->fd,
				'estado' => $datos_recibidos['estado'],
				'id_disp' => $datos_recibidos['id_disp'],
			]);
			break;

	}

});

//codigo temporal para verificar algun error con algun worker
$server->on('workerError', function(Server $server, int $workerId, int $workerPid, int $exitCode, int $signal){
    mensajesLog('CRITICO', "Worker crashed", [
        'worker_id' => $workerId,
        'pid' => $workerPid,
        'exit_code' => $exitCode,
        'signal' => $signal
    ]);
});

// Listen to the WebSocket connection close event.
$server->on('Close', function ($server, $fd) use ($dispAutenticados, $clientesWeb){
	mensajesLog('DESCONECTADO',	"Cliente desconectado", ['desconecatado' => $fd]);

	foreach($dispAutenticados as $id_disp => $datos){
		$fd_auth = $datos['fd'];
		if ($fd_auth == $fd){
			$dispAutenticados->del($id_disp);	
			mensajesLog('ELIMINADO', "Controlador eliminado de listas", ['eliminado' => $fd]);

			//envio de mensaje a los clientes web, que el controlador se ha desconectado del servidor
			try{
				$payloadDesconex = [
					'action' => 'control_desconex',
					'id_disp' => (string) $id_disp,
				];

				$server->task([
					'tipo' => 'emision_desconex',
					'payload' => $payloadDesconex,
				]);

				mensajesLog("INFO", "Estado de controlador desconectado enviado a los clientes", [
					'id_disp' => (string) $id_disp,
					'timestamp' => date('d/m H:i:s'),
				]);

			}
			catch(\Exception $e){
				mensajesLog("ERROR", "Error al enviar el estado de desconexion", ['error' => $e->getMessage()]);	
			}
		}
	}

	if($clientesWeb->exists($fd)){
		$clientesWeb->del($fd);
		mensajesLog('ELIMINADO', "Cliente web eliminado de listas", ['eliminado' => $fd]);
	}
});

$server->set([
	'worker_num' => 1,
	'task_worker_num' => 2, //workers para realizar tareas pesadas como la emision de datos
	'task_enable_coroutine' => true, //activando la corutinas
	'max_request' => 0, //infinitos requests
	'heartbeat_check_interval' => 10,
	'heartbeat_idle_time' => 20,
]);

$server->start();

?>

