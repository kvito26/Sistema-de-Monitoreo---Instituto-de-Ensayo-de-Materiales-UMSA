<?php
namespace Iem\WebSocket;

use Iem\OrmHelper;
use Iem\Ambiente\Ambiente;
use Iem\Dispositivo\Dispositivo;
use Iem\Dispositivo\DispositivoRepository;
use Iem\Ambiente\AmbienteRepository;

class SocketController {
    private DispositivoRepository $dispRepo;
    private AmbienteRepository $ambRepo;

    public function __construct() {
        // Obtenemos los repositorios usando el EntityManager de tu OrmHelper
        $this->dispRepo = new DispositivoRepository();
        $this->ambRepo = new AmbienteRepository();
    }

    public function registrarLectura(array $data): void {
        $em = OrmHelper::getEntityManager();
        
        try {
            // Buscamos el dispositivo por su identificador (ej: 'ESP32_01')
            $dispositivo = $this->dispRepo->findOneBy(['identificador' => $data['device_id']]);

            if ($dispositivo) {
                // Creamos la lectura usando tu entidad Ambiente
                $lectura = new Ambiente();
                $lectura->setTemp((float)$data['temp']);
                $lectura->setHum((float)$data['hum']);
                $lectura->setFecha(new \DateTimeImmutable());
                $lectura->setDispUbi($dispositivo);

                $this->ambRepo->insert($lectura);

                // Actualizamos última conexión del dispositivo
                $dispositivo->setUltimaConexion(new \DateTimeImmutable());
                $this->dispRepo->update($dispositivo);

                // Gestión de memoria: Limpiamos el Identity Map de Doctrine
                $em->clear();
                echo "Datos guardados para: " . $dispositivo->getNombreDisp() . "\n";
            }
        } catch (\Exception $e) {
            echo "Error en SocketController: " . $e->getMessage() . "\n";
        }
    }

    public function procesarFeedback(array $data): void {
        try {
            $dispositivo = $this->dispRepo->findOneBy(['identificador' => $data['device_id']]);
            if ($dispositivo) {
                // Actualizamos el estado real (booleano) en la DB
                $dispositivo->setEstado((bool)$data['new_state']);
                $dispositivo->setUltimaConexion(new \DateTimeImmutable());
                $this->dispRepo->update($dispositivo);
                
                OrmHelper::getEntityManager()->clear();
                echo "Feedback procesado: " . $data['device_id'] . " -> Estado: " . $data['new_state'] . "\n";
            }
        } catch (\Exception $e) {
            echo "Error Feedback: " . $e->getMessage() . "\n";
        }
    }
}
