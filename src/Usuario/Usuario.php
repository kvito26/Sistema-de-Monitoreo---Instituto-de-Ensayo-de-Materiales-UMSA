<?php
namespace Iem\Usuario;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'usuarios')]
class Usuario{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', nullable: false)]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\Column(type: 'string', length: 100, nullable: false)]
	private string $nombre;
	
	#[ORM\Column(type: 'string', length: 100, nullable: false)]
	private string $apellido;
	
	#[ORM\Column(type: 'string', length: 50, nullable: false)]
	private string $username;
	
	#[ORM\Column(type: 'string', length: 100, nullable: false)]
	private string $password;

	#[ORM\Column(type: 'boolean', options: ['default' => false])]
	private bool $estado;

	public function getId(): int{
		return $this->id;
	}
	public function setId(?int $id): void{
		$this->id = $id;
	}

	public function getNombre(): string{
		return $this->nombre;
	}
	public function setNombre(string $nombre): void{
		$this->nombre = $nombre;
	}

	public function getApellido(): string{
		return $this->apellido;
	}
	public function setApellido(string $apellido): void{
		$this->apellido = $apellido;
	}

	public function getUsername(): string{
		return $this->username;
	}
	public function setUsername(string $username): void{
		$this->username = $username;
	}

	public function getPassword(): string{
		return $this->password;
	}
	public function setPassword(string $password): void{
		$hashedPass = password_hash($password, PASSWORD_DEFAULT);
		$this->password = $hashedPass;
	}

	public function getEstado(): bool{
		return $this->estado;
	}
	public function setEstado(bool $estado): void{
		$this->estado = $estado;
	}

}

?>
