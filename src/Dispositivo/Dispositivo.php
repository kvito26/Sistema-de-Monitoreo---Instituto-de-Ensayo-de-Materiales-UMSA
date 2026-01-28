<?php
namespace Iem\Dispositivo;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dispositivos')]
class Dispositivo{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private ?int $id;
	
	#[ORM\Column(type: 'string', length: 20, nullable: false)]
	private string $identificador;
	
	#[ORM\Column(type: 'string', length: 80, nullable: false)]
	private string $nombre_disp;
	
	#[ORM\Column(type: 'boolean', options: ['default' => false], nullable: false)]
	private bool $estado;
	
	#[ORM\Column(type: 'datetime_immutable', nullable: false)]
	private \DateTimeImmutable $ultima_conexion;

	public function getId(): ?int{
		return $this->id;
	}	
	public function setId(?int $id): void{
		$this->id = $id;
	}

	public function getIdentificador(): string{
		return $this->identificador;
	}
	public function setIdentificador(string $identificador): void{
		$this->identificador = $identificador;
	}

	public function getNombreDisp(): string{
		return $this->nombre_disp;
	}
	public function setNombreDisp(string $nombre_disp): void{
		$this->nombre_disp = $nombre_disp;
	}

	public function getEstado(): bool{
		return $this->estado;
	}
	public function setEstado(bool $estado): void{
		$this->estado = $estado;
	}

	public function getUltimaConexion(): \DateTimeImmutable{
		return $this->ultima_conexion;
	}
	public function setUltimaConexion(\DateTimeImmutable $ultima_conexion): void{
		$this->ultima_conexion = $ultima_conexion;
	}
}



?>
