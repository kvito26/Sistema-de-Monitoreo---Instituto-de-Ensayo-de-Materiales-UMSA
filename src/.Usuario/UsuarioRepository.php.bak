<?php
namespace Iem\Usuario;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Iem\OrmHelper;
use Iem\Usuario\Usuario;

class UsuarioRepository extends EntityRepository{
	private EntityManager $entityManager;

	public function __construct(){
		$this->entityManager = OrmHelper::getEntityManager();
		$entityClass = Usuario::class;
		$entityMetadata = $this->entityManager->getClassMetadata($entityClass);
		parent::__construct($this->entityManager, $entityMetadata);
	}	

	public function insert(Usuario $usuario): int{
		$this->entityManager->persist($usuario);
		$this->entityManager->flush();

		return $usuario->getId();
	}

	public function update(Usuario $usuario): void{
		$this->entityManager->persist($usuario);
		$this->entityManager->flush();
	}

	public function delete(int $id): void{
		$usuario = $this->find($id);
		$this->entityManager->remove($usuario);
		$this->entityManager->flush();
	}

	public function deleteAll(): void{
		$usuarios = $this->findAll();
		foreach ($usuarios as $usuario){
			$this->entityManager->remove($usuario);
		}
		$this->entityManager->flush();
	}
}


?>
