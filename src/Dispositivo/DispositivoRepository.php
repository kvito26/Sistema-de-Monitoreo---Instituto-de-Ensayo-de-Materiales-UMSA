<?php
namespace Iem\Dispositivo;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Iem\OrmHelper;

class DispositivoRepository extends EntityRepository{
	private EntityManager $entityManager;

	public function __construct(){
		$this->entityManager = OrmHelper::getEntityManager();
		$entityClass = Dispositivo::class;
		$entityMetadata = $this->entityManager->getClassMetadata($entityClass);
		parent::__construct($this->entityManager, $entityMetadata);
	}

	public function insert(Dispositivo $dispositivo): int{
		$this->entityManager->persist($dispositivo);
		$this->entityManager->flush();

		return $dispositivo->getId();
	}

	public function update(Dispositivo $dispositivo): void{
		$this->entityManager->persist($dispositivo);
		$this->entityManager->flush();
	}

	public function delete(int $id): void{
		$dispositivo = $this->find($id);
		$this->entityManager->remove($dispositivo);
		$this->entityManager->flush();
	}

	public function deleteAll(): void{
		$dispositivos = $this->findAll();
		foreach ($dispositivos as $dispositivo){
			$this->entityManager->remove($dispositivo);
		}
		$this->entityManager->flush();
	}

}

?>
