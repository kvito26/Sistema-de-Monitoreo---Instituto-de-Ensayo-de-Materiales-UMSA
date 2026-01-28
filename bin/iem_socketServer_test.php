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

//creando una tabla en memoria compartida para guardar dispositivos conectados
$dispConectados = new Table(1024);
$dispConectados->column('fd', Table::TYPE_INT);
$dispConectados->create();

//creando los objetos servidor y ws_controller del mismo. objeto del repositorio de los dispositivos
$ws_server = new Server("0.0.0.0", 8080);
$ws_controller = new WSocketController();
$dispositivoRepository = new DispositivoRepository();

//cargando los dispositivos de la tabla de la DB
$dispRegistrados = $dispositivoRepository->findAll();

function existencia($dispositivo): bool{
	foreach($dispRegistrados as $dispRegistrado){
		$dispRegistrado->$dispositivoRepository->getIdentificador();
		if ($dispRegistrado === $dispositivo){
			return true;
		}
	}	
	return false;
}

//evento start (solo una ejecucion)
$ws_server->on("start", function(Server $server){
	echo "Servidor WebSocket IEM desplegado en el puerto 8080" . PHP_EOL;
	echo "Procesos en el Sistema Operativo:" . PHP_EOL;
	echo "\tMaster PID: " . $server->master_pid . PHP_EOL;
	echo "\tManager PID: " . $server->manager_pid . PHP_EOL;
	echo "Para detener el servidor <Ctrl+C>" . PHP_EOL;
});

//evento open (se ejecuta cada vez que haya un nuevo cliente)
$ws_server->on("open", function(Server $server, Request $request){
	echo "Nueva conexion establecida: " . PHP_EOL;
	echo "\tFD (Descripcion de Archivo->Identificador Unica de Conexion): " . $request->fd . PHP_EOL;
	echo "\tDireccion IP del dispositivo conectado: " . $request->server['remote_addr'] . PHP_EOL;

	//enviando un saludo al nuevo dispositivo conectado
	$server->push($request->fd, json_encode([
		"estado" => "conectado",
		"mensaje" => "Te has conectado al servidor WebSocket del IEM-UMSA, si eres un desconocido, desconectate. El evento fue reportado.",
	]));
});

//evento message (logica de funcionamiento del servidor), recibir datos json
$ws_server->on("message", function (Server $server, Frame $frame) use ($dispConectados, $ws_controller){
	//usando la propiedad el objeto data
	$datos_recibidos = json_decode($frame->data, true);
	if (!$datos_recibidos){
		echo "Se ha recibido un mensaje invalido del FD: " . $frame->fd . PHP_EOL;	
		return;
	}
		
	switch ($datos_recibidos['action']){
		//caso para autenticacion
		case 'autenticacion':
			$dispConectados->set($datos_recibidos['id_disp'], ['fd' => $frame->fd]);
			echo "Dispositivo: " . $datos_recibidos['id_disp'] . " se ha autenticado en el FD: " . $frame->fd . PHP_EOL;
			break;

		//caso para recibir datos de temp y hum
		case 'datos_TH':
			$ws_controller->registroTH($datos_recibidos);
			date_default_timezone_set('America/La_Paz');	

			//enviando los datos recogidos en la web, para que lo recoja js
			$payloadWeb = json_encode([
				'action' => 'datos_actualizados',
				'id_disp' => $datos_recibidos['id_disp'],
				'temp' => $datos_recibidos['temp'],
				'hum' => $datos_recibidos['hum'],
				'fecha' => date('d/m H:i:s'),
			]);

			//evitar reenviar a los dispositivos registrados en la tabla de la DB
			

			foreach ($server->connections as $fd){
				if ($server->isEstablished($fd) && $fd != $frame->fd){
					$server->push($fd, $payloadWeb);
				}
			}
			
			echo "Datos de " . $datos_recibidos['id_disp'] . " difundidos a todos los clientes\n" . PHP_EOL;
			break;

		//caso para enviar comandos al microcontrolador
		case 'comando':
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
			$ws_controller->procesoFeedback($datos_recibidos);

			//enviar los datos a todos los clientes para actualizar sus ventanas
			foreach($server->connections as $fd){
				if($server->isEstablished($fd) && $fd != $frame->fd){
					$server->push($fd, json_encode($datos_recibidos));
				}
			}

			break;
		

		default:
			echo "llave erronea, alv" . PHP_EOL;
			break;

	}

});

// Listen to the WebSocket connection close event.
$ws_server->on('Close', function ($server, $fd) {
    echo "client-{$fd} is closed\n";
});



$ws_server->set([
	'worker_num' => 3,
	'max_request' => 1024,
	'heartbeat_check_interval' => 30,
	'heartbeat_idle_time' => 60,
]);

$ws_server->start();

?>

