<?php
//controlador de websocket para el mismo servidor
namespace Iem\WebSocket;

use Iem\Ambiente\Ambiente;
use Iem\Ambiente\AmbienteRepository;
use Iem\Dispositivo\Dispositivo;
use Iem\Dispositivo\DispositivoRepository;
use Iem\OrmHelper;

class WSocketController{
	private DispositivoRepository $dispositivoRepository;
	private AmbienteRepository $ambienteRepository;

	public function __construct(){
		$this->dispositivoRepository = new DispositivoRepository();
		$this->ambienteRepository = new AmbienteRepository();
	}

	//funcion para el registro de lectura de los sensores
	//recibiendo un array de datos
	public function registroTH(array $datos): void{
		//llamando al entityManager de Doctrine
		$em = OrmHelper::getEntityManager();
		try {
			//buscando el dispositivo desde el array recibido
			$disp = $this->dispositivoRepository->findOneBy([
				'identificador' => $datos['id_disp']
			]);

			//verificando si el dispositivo existe o es null
			if ($disp){
				//obteniendo los datos 
				$temp = (float) $datos['temp'];
				$hum = (float) $datos['hum'];
				$fecha = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));

				//se guardan los datos de la temp y hum
				//creando un objeto ambiente para guardar los datos
				$lectura = new Ambiente();
				$lectura->setFecha($fecha);
				$lectura->setTemp($temp);
				$lectura->setHum($hum);
				$lectura->setDispositivo($disp);

				//guardando en la db
				$this->ambienteRepository->insert($lectura);

				//actualizando la ultima conexion del dispositivo
				$disp->setUltimaConexion($fecha);
				$this->dispositivoRepository->update($disp);

				//limpiando doctrine para liberar memoria
				$em->clear();
				
				//echo "Se ha guardado la temp: " . $lectura->getTemp() . " y la hum: " . $lectura->getHum() . " enviado desde el: " . $disp->getNombreDisp() . " a la fecha: " . var_dump($lectura->getFecha()) . PHP_EOL;
				//echo "-------------------------------------------------" . PHP_EOL;
			}

		}
		
		catch (\Exception $e){
			echo "Hubo un error en el WSocketController.php (function registroTH()): " . $e->getMessage() . PHP_EOL;
		}
	}

	//funcion para procesar feedback del dispositivo (si es que esta vivo);
	public function procesoFeedback(array $datos): void{
		//llamando al entityManager de Doctrine
		$em = OrmHelper::getEntityManager();
		try{
			//buscando el dispositivos en los datos recibidos
			$disp = $this->dispositivoRepository->findOneBy(['identificador' => $datos['id_disp']]);

			//si el dispositivo fue encontrado
			if ($disp){
				$fecha = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));
				//actualizando el estado del dispositivo
				$disp->setEstado((bool) $datos['estado_ahora']);
				$disp->setUltimaConexion($fecha);
				$this->dispositivoRepository->update($disp);
			}
			
			//haciendo una limpieza en el orm para no ocupar ram
			$em->clear();
		
			//echo "Feedback hecho para: " . $datos['id_disp'] . " con el estado: " . $datos['estado_ahora'] . PHP_EOL;
		}
		catch (\Exception $e){
			echo "Hubo un error en el WSocketController.php (function procesoFeedback()): " . $e->getMessage() . PHP_EOL;
		}
	}
		



}




?>
