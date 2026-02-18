<?php
require('../fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(40,10,'este es el primer pdf creado por mi');
$pdf->Output();
?>
