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

//creando una tabla en memoria compartida para guardar dispositivos autenticados
$dispAutenticados = new Table(100);
$dispAutenticados->column('fd', Table::TYPE_INT);
$dispAutenticados->create();

//creando una tabla en memoria compartida para guardar los clientes web
$clientesWeb = new Table(1024);
$clientesWeb->column('hora_conexion', Table::TYPE_INT);
$clientesWeb->create();

//creando los objetos servidor y controller del mismo. objeto del repositorio de los dispositivos
$server = new Server("0.0.0.0", 8080);
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
	$clientesWeb->set($request->fd, ['hora_conexion' => time()]);
	mensajesLog('INFO', "Conexion guardada por defecto en clientes web", ['fd' => $request->fd]);


	//enviando mensaje al cliente
	try{
		$server->push($request->fd, $mensajeBienvenida);
	}
	catch(Exception $e){
		mensajesLog('ERROR', "Error al enviar el mensaje de bienvenida", ['error' => $e->getMenssage()]);
	}
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
				//borrando de la tabla de clientes web y agregando a la tabla de registrados (autenticados)
				echo "es el cliente web: " . $clientesWeb->get($frame->fd) . PHP_EOL;
				$clientesWeb->del($frame->fd);
				mensajesLog('INFO', "Cliente borrado de lista webs", ['fd' => $frame->fd]);
			}

			$dispAutenticados->set($datos_recibidos['id_disp'], ['fd' => $frame->fd]);

			//log de autenticado y guardado en la tabla
			mensajesLog("AUTENTICADO", "Se ha autenticado y guardado un nuevo dispositivo", ['dispositivo' => $datos_recibidos['id_disp']]);

			break;


		//caso para recibir datos de temp y hum
		case 'datos_TH':
			try{
				//registrando los datos en la DB
				$controller->registroTH($datos_recibidos);

				//enviando los datos recogidos en la web, para que lo recoja js
				$payloadWeb = json_encode([
					'action' => 'datos_actualizados',
					'id_disp' => $datos_recibidos['id_disp'],
					'temp' => $datos_recibidos['temp'],
					'hum' => $datos_recibidos['hum'],
					'fecha' => date('d/m H:i:s'),
				]);

				mensajesLog("DATOS", "Datos registrados y emitidos", [
					'ambiente' => $datos_recibidos['id_disp'],
					'temp' => $datos_recibidos['temp'],
					'hum' => $datos_recibidos['hum'],
					'timestamp' => date('d/m H:i:s'),
				]);

				//reenviando a todos los clientes web
				foreach($clientesWeb as $fd => $hora_conexion){
					if ($server->isEstablished($fd)){
						try{
							echo "**********estrellitas********" . PHP_EOL;
							$server->push($fd, $payloadWeb);
							mensajesLog('INFO', "Mensaje emitido al cliente web", ['cliente_receptor' => $fd]);
						}
						catch(\Exception $e){
							mensajesLog('ERROR', "Error al hacer el feedback al cliente web", ['error_feedback' => $fd]);

						}
					}
				}
			//	foreach ($server->connections as $fd){
			//		if ($server->isEstablished($fd) && $fd != $frame->fd && !isset($fd_registrados[$fd])){
			//			$server->push($fd, $payloadWeb);
			//			mensajesLog('INFO', "Dispositivos evitatos", ['evitados' => implode(", ", $fd_registrados)]);
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

			try{
				//verificando la existencia del dispositivo objetivo
				if ($disp_objetivo && $server->isEstablished($disp_objetivo['fd'])){
					mensajesLog('INFO', "El dispositivo esta autenticado", [
						'dispositivo' => $datos_recibidos['id_objetivo'],
						'tipo_comando' => $datos_recibidos['tipo'],
					]);
					
					//enviando la orden al dispositivo
					$mensaje = json_encode([
						'tipo' => $datos_recibidos["tipo"],
						'valor' => $datos_recibidos["valor"],
					]);

					$server->push($disp_objetivo['fd'], $mensaje);
					mensajesLog('COMANDO', "Se ha enviado el comando", [
						'dispositivo' => $datos_recibidos['id_objetivo'],
						'tipo_comando' => $datos_recibidos['tipo'],
						'valor' => $datos_recibidos["valor"],
					]);
				}
			}
			catch(\Exception $e){
				mensajesLog('ERROR', "No se ha podido enviar el comando", ['error_comando' => [
					'dispositivo' => $datos_recibidos['id_objetivo'],
					'tipo_comando' => $datos_recibidos['tipo'],
				]]);
			}
			break;

		//caso para el feedback enviado por el dispositivo
		case 'feedbackVent': case 'feedbackCalent': case 'feedbackHumi':
			try{
				$controller->procesoFeedback($datos_recibidos);

				foreach($server->connections as $fd){
					echo "Cliente: " . $clientesWeb->get($fd) . PHP_EOL;
					if($server->isEstablished($fd) && $clientesWeb->get($fd)){
						echo "**********estrellitas********" . PHP_EOL;
						echo "fd web: " . $fd . PHP_EOL;
						try{
							$server->push($fd, json_encode($datos_recibidos));
							mensajesLog('INFO', "Feedback emitido al cliente web", ['cliente_receptor' => $fd]);
						}
						catch(\Exception $e){
							mensajesLog('Error', "Error al hacer el feedback al cliente web", ['error_feedback' => $fd]);
						}
					}
				}
				//enviar los datos a todos los clientes para actualizar sus ventanas
			//	foreach($server->connections as $fd){
			//		if($server->isEstablished($fd) && $fd != $frame->fd && !isset($fd_registrados[$fd])){
			//			$server->push($fd, json_encode($datos_recibidos));
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

// Listen to the WebSocket connection close event.
$server->on('Close', function ($server, $fd) use ($dispAutenticados, $clientesWeb){
	if($clientesWeb->exists($fd)){
		$clientesWeb->del($fd);
		mensajesLog('ELIMINADO', "Cliente web eliminado de listas", ['eliminado' => $fd]);
	}
	if($dispAutenticados->exists($fd)){
		$dispAutenticados->del($fd);	
		mensajesLog('ELIMINADO', "Controlador eliminado de listas", ['eliminado' => $fd]);
	}
	mensajesLog('DESCONECTADO',	"Cliente desconectado", ['desconecatado' => $fd]);
});

$server->set([
	'worker_num' => 4,
	'max_request' => 1024,
	'heartbeat_check_interval' => 10,
	'heartbeat_idle_time' => 20,
]);

$server->start();

?>

