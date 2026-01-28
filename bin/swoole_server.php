<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php'; 

use Iem\OrmHelper;
use Iem\WebSocket\SocketController;
use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use Swoole\Table;
use Swoole\Http\Request;


// 1. Inicialización de componentes persistentes
OrmHelper::setEntityManager($entityManager);

$dispositivosConectados = new Table(100);
$dispositivosConectados->column('fd', Table::TYPE_INT);
$dispositivosConectados->create();

$server = new Server("0.0.0.0", 8080);
$controller = new SocketController();

/**
 * EVENTO: Start
 * Se ejecuta una sola vez cuando el proceso maestro inicia.
 */
$server->on("start", function (Server $server) {
    echo "Servidor Swoole IEM iniciado en http://0.0.0.0:8080\n";
    echo "Master PID: {$server->master_pid} | Manager PID: {$server->manager_pid}\n";
    echo "Presiona Ctrl+C para detener el servidor.\n";
    echo "--------------------------------------------------------\n";
});

/**
 * EVENTO: Open
 * Se ejecuta cuando un nuevo cliente (ESP32 o Navegador) abre una conexión.
 */
$server->on("open", function (Server $server, Request $request) {
    echo "Nueva conexión abierta: FD {$request->fd} | IP: {$request->server['remote_addr']}\n";
    
    // Opcional: Enviar un saludo de bienvenida o verificar origen
    $server->push($request->fd, json_encode([
        "status" => "connected",
        "message" => "Conexión establecida con el servidor IEM"
    ]));
});

/**
 * EVENTO: Message
 * El núcleo de tu lógica: recibe y procesa datos JSON.
 */
$server->on("message", function (Server $server, $frame) use ($dispositivosConectados, $controller) {
    $data = json_decode($frame->data, true);
    if (!$data) {
        echo "Mensaje inválido recibido de FD {$frame->fd}\n";
        return;
    }

    switch ($data['action']) {
        case 'auth':
            $dispositivosConectados->set($data['device_id'], ['fd' => $frame->fd]);
            echo "Dispositivo '{$data['device_id']}' autenticado en FD {$frame->fd}\n";
            break;

        case 'sensor_data':
            $controller->registrarLectura($data);
            break;

        case 'command':
            $target = $dispositivosConectados->get($data['target_id']);
            if ($target && $server->isEstablished($target['fd'])) {
            //if ($target) {
                $server->push($target['fd'], json_encode([
                    'tipo' => 'orden',
                    'valor' => $data['valor']
                ]));
                echo "Comando enviado a {$data['target_id']}: {$data['valor']}\n";
            } else {
                echo "Error: Dispositivo {$data['target_id']} no está en línea.\n";
            }
            break;

        case 'feedback':
            $controller->procesarFeedback($data);
            // Broadcast a todos para actualizar interfaces web en tiempo real
            foreach ($server->connections as $fd) {
                if ($server->isEstablished($fd)) {
                    $server->push($fd, json_encode($data));
                }
            }
            break;
            
        default:
            echo "Acción desconocida: {$data['action']}\n";
            break;
    }
});

/**
 * EVENTO: Close
 * Se ejecuta cuando un cliente se desconecta o la conexión se pierde.
 */
$server->on("close", function (Server $server, int $fd) use ($dispositivosConectados) {
    echo "Conexión cerrada: FD {$fd}\n";
    
    // Limpieza de la tabla de memoria para no mantener FDs obsoletos
    foreach ($dispositivosConectados as $id => $row) {
        if ($row['fd'] === $fd) {
            $dispositivosConectados->del($id);
            echo "Memoria liberada: Dispositivo '$id' removido de la tabla activa.\n";
            break;
        }
    }
});

/**
 * CONFIGURACIÓN ADICIONAL DE EFICIENCIA
 */
$server->set([
    'worker_num' => 2,              // Número de procesos worker
    'max_request' => 1000,          // Reiniciar worker tras 1000 peticiones para prevenir fugas de RAM
    'heartbeat_check_interval' => 30, // Comprobar conexiones muertas cada 30s
    'heartbeat_idle_time' => 60,      // Cerrar si no hay actividad en 60s
]);

$server->start();
