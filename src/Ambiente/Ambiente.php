<?php
namespace Iem\Ambiente;

use Doctrine\ORM\Mapping as ORM;
use Iem\Dispositivo\Dispositivo;

#[ORM\Entity]
#[ORM\Table(name: 'ambientes')]
class Ambiente{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', nullable: false)]
	#[ORM\GeneratedValue]
	private ?int $id;

	#[ORM\Column(type: 'datetime_immutable', nullable: false)]
	private \DateTimeImmutable $fecha;
	
	#[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: false)]
	private float $temp;
	
	#[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: false)]
	private float $hum;

	#[ORM\ManyToOne(targetEntity: Dispositivo::class)]
	#[ORM\JoinColumn(name: 'disp_ubi', referencedColumnName: 'id', nullable: false)]
	private ?Dispositivo $dispositivo;

	public function getId(): ?int{
		return $this->id;
	}
	public function setId(?int $id): void{
		$this->id = $id;
	}

	public function getFecha(): \DateTimeImmutable{
		return $this->fecha;
	}
	public function setFecha(\DateTimeImmutable $fecha): void{
		$this->fecha = $fecha;
	}

	public function getTemp(): float{
		return $this->temp;
	}
	public function setTemp(float $temp): void{
		$this->temp = $temp;
	}

	public function getHum(): float{
		return $this->hum;
	}
	public function setHum(float $hum): void{
		$this->hum = $hum;
	}

	public function getDispositivo(): ?Dispositivo{
		return $this->dispositivo;
	}
	public function setDispositivo(?Dispositivo $dispositivo): void{
		$this->dispositivo = $dispositivo;
	}
}

?>
