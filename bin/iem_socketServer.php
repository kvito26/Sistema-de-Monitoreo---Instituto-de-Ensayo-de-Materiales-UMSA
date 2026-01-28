<?php
//codigo para implementar el servidor websocket
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php';

use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use Swoole\Table;
use Swoole\Http\Request;
use Swoole\Timer;
use Iem\WebSocket\WSocketController;
use Iem\OrmHelper;
use Iem\Dispositivo\DispositivoRepository;

OrmHelper::setEntityManager($entityManager);
date_default_timezone_set('America/La_Paz');	

//tabla 1: dispositivos conectados
$dispConectados = new Table(2048);
$dispConectados->column('fd', Table::TYPE_INT);
$dispConectados->column('ip', Table::TYPE_STRING, 64);
$dispConectados->column('hora_conexion', Table::TYPE_INT);
$dispConectados->create();

//tabla 2: auxiliar para identificacion de registro
$fd_registrados = new Table(2048);
$fd_registrados->column('esta_reg', Table::TYPE_INT);
$fd_registrados->column('id_disp', Table::TYPE_STRING, 64);
$fd_registrados->column('ultima_actividad', Table::TYPE_INT);
$fd_registrados->create();

$lastFeeds = [];

//creando los objetos servidor y controller del mismo. objeto del repositorio de los dispositivos
$server = new Server("0.0.0.0", 8080);
$controller = new WSocketController();
$dispositivoRepository = new DispositivoRepository();

//cargando los dispositivos registrados
$identiIds = [];
try{
	$dispRegistrados = $dispositivoRepository->findAll();

	foreach($dispRegistrados as $dispRegistrado){
		$identiIds[] = $dispRegistrado->getIdentificador();
	}

	echo "\nSe tiene los siguientes dispositivos registrados en la DB: " .implode(", ", $identiIds) . PHP_EOL;
}
catch (EXception $e){
	echo "Error al recuperar los dispositivos registrados de la DB | " . $e->getMessage() . PHP_EOL;
}

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
		'DATOS' => "\033[34m", //azul
		'FEEDBACK' => "\033[35m", //magenta
		'TAREA' => "\033[36m", //cyan
	];

	$color = $colores[$nivel] ?? $colores['RESET'];
	$reset = $colores['RESET'];

	//convirtiendo a json
	$contexto_json = !empty($contexto) ? ' | ' . json_encode($contexto) : '';

	$logLine = "[{$timestamp}] [{$color}{$nivel}{$reset}] {$mensaje}{$contexto_json}" . PHP_EOL;

	echo $logLine;
}

//funcion para actualizar la actividad en un dispositivo 
function actualizarActividad(Table $fd_registrados, int $fd): void{
	$row = $fd_registrados->get((string)$fd);
	if($row){
		$fd_registrados->set((string)$fd, array_merge($row, [
			'ultima_actividad' => time(),
		]));
	}
}

//function de validacion de estructura de datos recibidos
function validarDatosRecibidos(?array $datos, array $camposRequeridos): bool{
	if(!$datos){
		return false;
	}
	foreach($camposRequeridos as $campo){
		if(!isset($datos[$campo])){
			return false;
		}
	}

	return true;
}

//funcion para la emision de datos a los clientes no registrados
function emisionDatos(Server $server, Table $fd_registrados, int $origenFD, string $payload): void{
	$emitido = 0; //contador

	foreach($server->connections as $fd){
		if (!$server->isEstablished($fd) || $fd === $origenFD){
			continue;
		}

		$row = $fd_registrados->get((string)$fd);
		$esta_reg = $row ? (int)$row['esta_reg'] : 0;

		//emitiendo a los clientes no registrados
		if($esta_reg === 0){
			try{
				$server->push($fd, $payload);
				$emitido++;
			}
			catch (Exception $e){
				mensajesLog('ERROR', "Error al emitir datos a FD {$fd}: " . $e->getMessage());
			}
		}
	}
	
	if($emitido > 0){
		mensajesLog('INFO', "Datos emitidos a {$emitido} cliente(s)");
	}

}


//**************************************************************************************
//WORKER para la emision masiva
$server->on('WorkerStart', function(Server $server, int $workerId) use (&$lastFeeds, $fd_registrados){
	mensajesLog('INFO', "Worker #{$workerId} iniciado");
	
	//trabajando el worker 0 para la emision de datos
	if ($workerId === 0){
		Timer::tick(100, function() use ($server, &$lastFeeds, $fd_registrados){
			if(empty($lastFeeds)){
				return;
			}

			//conteo de los feedbacks emitidos
			$total_emitido = 0;

			foreach($lastFeeds as $id_disp => $componentes_json){
				foreach($componente as $comp => $json_payload){
					foreach($server->connections as $fd){
						if (!$server->isEstablished($fd)){
							continue;
						}

						$row = $fd_registrados->get((string)$fd);
						$esta_reg = $row ? (int)$row['esta_reg'] : 0;

						//solo emitir a los que nos estan registrados
						if($esta_reg === 0){
							try{
								$server->push($fd, $json_payload);
								$total_emitido++;
							}
							catch(Exception $e){
								mensajesLog('ERROR', "Error en la emision general a FD: {$fd}", ['error' => $e->getMessage()]);
							}
						}
					}
				}
			}

			if ($total_emitido > 0){
				mensajesLog('DEBUG', "Se ha emitido feedbacks periodicos", ['total_emitido' => $total_emitido]);
			}
		
		});

	mensajesLog('INFO', "Timer para emision masiva activado");	

	}

});

//evento INICIO (solo una ejecucion)
$server->on("start", function(Server $server){
	echo str_repeat("=", 60) . PHP_EOL;
	echo "Servidor WebSocket IEM desplegado en el puerto 8080" . PHP_EOL;
	echo "Procesos en el Sistema Operativo:" . PHP_EOL;
	echo "\tMaster PID: " . $server->master_pid . PHP_EOL;
	echo "\tManager PID: " . $server->manager_pid . PHP_EOL;
	echo "Para detener el servidor <Ctrl+C>" . PHP_EOL;
	echo str_repeat("=", 60) . PHP_EOL;
});

//evento OPEN (se ejecuta cada vez que haya un nuevo cliente)
$server->on("open", function(Server $server, Request $request) use ($fd_registrados){
	//obteniendo la direccion ip del nuevo dispositivo
	$ip = $request->server['remote_addr'] ?? 'desconocida';

	mensajesLog('CONECTADO', "Nueva Conexion", [
		'fd' => $request->fd,
		'ip' => $ip,
	]);

	//agragar en tabla de registro, por defecto cliente no registrado
	$fd_registrados->set((string)$request->fd, [
		'esta_reg' => 0,
		'id_disp' => '',
		'ultima_actividad' => time(),
	]);

	//mensaje de bienvenida al nuevo dispositivo
	$mensajeBienvenida = json_encode([
		"estado" => "conectado",
		"mensaje" => "Te has conectado al servidor WebSocket del IEM-UMSA, si eres un desconocido, desconectate. El evento fue reportado.",
		"timestamp" => time(),
		"fd" => $request->fd,
	]);

	//enviando mensaje al cliente
	try{
		$server->push($request->fd, $mensajeBienvenida);
	}
	catch(Exception $e){
		mensajesLog('ERROR', "Error al enviar el mensaje de bienvenida", ['error' => $e->getMenssage()]);
	}

});

//evento message (logica de funcionamiento del servidor), recibir datos json
$server->on("message", function (Server $server, Frame $frame) use ($dispConectados, $controller, $fd_registrados, $identiIds, ){
	//actualizar la actividad del remitente
	actualizarActividad($fd_registrados, $frame->fd);

	//decodificar datos desde del json
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
		case 'autenticacion':
			$dispConectados->set($datos_recibidos['id_disp'], ['fd' => $frame->fd]);
			if (!validarDatosRecibidos($datos_recibidos, ['id_disp'])){
				mensajesLog('ADVERTENCIA', "Autenticacion fallida: sin 'id_disp'", ['fd' => $frame->fd]);
				break;
			}

			$id = $datos_recibidos['id_disp'];
			//viendo si esta registrado o no
			$esta_registrado = in_array($id, $identiIds, true) ? 1 : 0;

			//obteniendo en la direccion ip del dispositivo
			$clientInfo = $server->getClientInfo($frame->fd);
			$ip = $clientInfo['remote_ip'] ?? 'desconocida';

			//guardando en tabla principal
			$dispConectados->set($id, [
				'fd' => $frame->fd,
				'ip' => $ip,
				'hora_conexion' => time(),
			]);

			//actualizando tabla auxiliar
			$fd_registrados->set((string)$frame->fd, [
				'esta_reg' => $esta_registrado,
				'id_disp' => $id,
				'ultima_actividad' => time(),
			]);

			//mensaje de confirmar la autenticacion al cliente
			$respuesta_auth = json_encode([
				'action' => 'autenticacion_confirmanda',
				'registrado' => (bool) $esta_registrado,
				'timestamp' => time(),
			]);
			
			//enviar e imprimir log depende su registro
			try{
				if ($esta_registrado){
					mensajesLog('AUTENTICADO', "Se ha autenticado un dispositivo registrado", ['dispositivo' => $id]);
				}
				else {
					mensajesLog('AUTENTICADO', "Se ha autenticado un dispositivo NO registrado", ['dispositivo' => $id]);
				}
				$server->push($frame->fd, $respuesta_auth);
			}
			catch(Exception $e){
				mensajesLog('ERROR', "Error al enviar la confirmacion de autenticacion", ['error' => $e->getMessage()]);
			}
			break;

		//caso para recibir datos de temp y hum
		case 'datos_TH':
			//validar los datos recibidos de temp y hum
			if (!validarDatosRecibidos($datos_recibidos, ['id_disp', 'temp', 'hum'])){
				mensajesLog('ERROR', "Datos TH incompletos", ['fd' => $frame->fd]);
				break;
			}

			try{
				//registro en la base de datos		
				$controller->registroTH($datos_recibidos);
			
				//enviando los datos recogidos en la web, para que lo recoja js
				$payloadWeb = json_encode([
					'action' => 'datos_actualizados',
					'id_disp' => $datos_recibidos['id_disp'],
					'temp' => $datos_recibidos['temp'],
					'hum' => $datos_recibidos['hum'],
					'fecha' => date('d/m H:i:s'),
				]);

				//emitiendo datos a dispositivos no registrados
				emisionDatos($server, $fd_registrados, $frame->fd, $payloadWeb);

				mensajesLog('DATOS', "Datos TH procesados y difundidos a los clientes", [
					'id' => $datos_recibidos['id_disp'],
					'temp' => $datos_recibidos['temp'],
					'hum' => $datos_recibidos['hum'],
				]);

			}
			catch(Exception $e){
				mensajesLog('ERROR', "Error al procesar y difundir datos TH", ['error' => $e->getMessage]);	
			}

		//caso para enviar comandos al microcontrolador
		case 'comando':
			if (!validarDatosRecibidos($datos_recibidos, ['id_objetivo', 'tipo', 'valor'])){
				mensajesLog('ERROR', "El comando recibido esta incompleto", ['fd' => $frame->fd]);
				break;
			}

			$id_objetivo = $datos_recibidos['id_objetivo'];
			$disp_objetivo = $dispConectados->get($id_objetivo);

			if ($disp_objetivo && $server->isEstablished($disp_objetivo['fd'])){
				$comando = json_encode([
					'tipo' => $datos_recibidos['tipo'],
					'valor' => $datos_recibidos['valor'],
				]);

				try{
					$server->push($disp_objetivo['fd'], $comando);
					mensajesLog('COMANDO', "Comando enviado", [
						'dispositivo' => $id_objetivo,
						'fd_dispositivo' => $disp_objetivo['fd'],
						'tipo' => $datos_recibidos['tipo'],
						'valor' => $datos_recibidos['valor'],
					]);
				}
				catch(Exception $e){
					mensajesLog('ERROR', "Error al enviar el comando", ['Error' => $e->getMessage()]);
				}

			}
				


			$disp_objetivo = $dispConectados->get($datos_recibidos['id_objetivo']);
			//verificando la existencia del dispositivo objetivo
			if ($disp_objetivo && $server->isEstablished($disp_objetivo['fd'])){
				echo "EL DISPOSITIVO EXISTE YEI \n";
				//enviando la orden al dispositivo
				$mensaje = json_encode([
					'tipo' => $datos_recibidos["tipo"],
					'valor' => $datos_recibidos["valor"],
				]);
				$server->push($disp_objetivo['fd'], $mensaje);
				echo "comando envido al perro :/ \n";
			}
			else {
				echo "aqui no conocemos a ese wey :/ \n";
			}
			break;

		//caso para el feedback enviado por el dispositivo
		case 'feedbackVent': case 'feedbackCalent': case 'feedbackHumi':
			$row = $fd_registrados->get((string)$frame->fd);
			$id = $row && !empty($row['id_disp']) ? $row['id_disp'] : 'desconocido';

			try{
				//guardando actualizacion en la base de datos
				$controller->procesoFeedback($datos_recibidos);

				//A REVISAR
				$componente = $datos_recibidos['componente'] ?? match($action) {
				'feedbackVent' => 'ventilador',
				'feedbackCalent' => 'calentador',
				'feedbackHumi' => 'humidificador',
				default => 'general',
				};

				$lastFeeds[$id][$componente] = json_encode($datos_recibidos);

				mensajesLog('FEEDBACK', "Feedback recibido y emitido", [
					'id' => $id,
					'tipo' => $action,
					'componente' => $componente,
				]);

				emisionDatos($server, $fd_registrados, $frame->fd, json_encode($datos_recibidos));

			}
			catch(Exception $e){
			
			}
			break;
		

		default:
			echo "llave erronea, alv" . PHP_EOL;
			break;

	}

});

// Listen to the WebSocket connection close event.
$server->on('Close', function ($server, $fd) {
    echo "client-{$fd} is closed\n";
});



$server->set([
	'worker_num' => 2,
	'max_request' => 1024,
	'heartbeat_check_interval' => 30,
	'heartbeat_idle_time' => 60,
]);

$server->start();

?>

