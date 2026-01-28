<?php
namespace Iem\Ambiente;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Iem\OrmHelper;

class AmbienteRepository extends EntityRepository{
	private EntityManager $entityManager;

	public function __construct(){
		$this->entityManager = OrmHelper::getEntityManager();
		$entityClass = Ambiente::class;
		$entityMetadata = $this->entityManager->getClassMetadata($entityClass);
		parent::__construct($this->entityManager, $entityMetadata);
	}

	public function insert(Ambiente $ambiente): int{
		$this->entityManager->persist($ambiente);
		$this->entityManager->flush();

		return $ambiente->getId();
	}

	public function update(Ambiente $ambiente): void{
		$this->entityManager->persist($ambiente);
		$this->entityManager->flush();
	}

	public function delete(int $id): void{
		$ambiente = $this->find($id);
		$this->entityManager->remove($ambiente);
		$this->entityManager->flush();
	}

	public function deleteAll(): void{
		$ambientes = $this->findAll();
		foreach ($ambientes  as $ambiente){
			$this->entityManager->remove($ambiente);
		}
		$this->entityManager->flush();
	}

	//funcion personalizada, encontrar el ultimo valor por dispositivo
	public function findLastRowByDevice(string $id_disp): ?Ambiente{
		$ultima_lectura = $this->entityManager->createQuery(
			'SELECT a
			FROM Iem\Ambiente\Ambiente a
			JOIN a.dispositivo d
			WHERE d.identificador = :id_disp
			ORDER BY a.fecha DESC'
		)
		->setParameter('id_disp', $id_disp)
		->setMaxResults(1)
		->getOneOrNullResult();

		return $ultima_lectura;
	}


}

?>
