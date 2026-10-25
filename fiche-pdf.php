<?php
/**
 * fiche-pdf.php — fiche d'inscription des équipes, vierge (PDF, en-tête et pied
 * des impressions d'Ianseo).
 *
 * À envoyer aux clubs avant la compétition : ils la remplissent à la main,
 * l'organisateur compose ensuite les équipes dans l'assistant. Une page remplie
 * de petits tableaux sur deux colonnes : en-tête « Équipe / Club » et « Catégorie »,
 * puis 4 lignes d'archers (nom, catégorie). Lecture seule : rien n'est écrit.
 */
require_once __DIR__ . '/config.php';
require_once 'Common/pdf/ResultPDF.inc.php';

const EQUIPE_FICHE_ARCHERS = 4;

$pdf = new ResultPDF('Fiche d\'inscription des équipes');
$L = $pdf->getSideMargin();
$W = $pdf->GetPageWidth() - 2 * $L;
$limit = $pdf->GetPageHeight() - 22; // au-dessus du pied de page d'Ianseo

$gap = 6;
$tw = ($W - $gap) / 2;                                   // largeur d'un tableau
$cols = [['N°', 8, 'C'], ['Nom et prénom', $tw - 8 - 26, 'L'], ['Catégorie', 26, 'C']];
$rowH = 6.5;
$th = 7 + 4.5 + $rowH * EQUIPE_FICHE_ARCHERS;              // hauteur d'un tableau
$vgap = 3;

/** Un tableau d'équipe dont le coin haut gauche est en ($x, $y). */
$table = function ($x, $y) use ($pdf, $tw, $cols, $rowH) {
    // En-tête : nom et catégorie de l'équipe, à écrire.
    $pdf->SetXY($x, $y);
    $pdf->SetFont($pdf->FontStd, 'B', 9);
    $pdf->Cell($tw * 0.62, 7, 'Équipe / Club :', 1, 0, 'L', 1);
    $pdf->Cell($tw * 0.38, 7, 'Catégorie :', 1, 1, 'L', 1);
    $pdf->SetX($x);
    $pdf->SetFont($pdf->FontStd, 'B', 7);
    foreach ($cols as [$label, $w]) $pdf->Cell($w, 4.5, $label, 1, 0, 'C');
    $pdf->SetFont($pdf->FontStd, '', 8);
    for ($r = 1; $r <= EQUIPE_FICHE_ARCHERS; $r++) {
        $pdf->SetXY($x, $pdf->GetY() + ($r === 1 ? 4.5 : $rowH));
        foreach ($cols as $i => [, $w, $align]) $pdf->Cell($w, $rowH, $i === 0 ? (string) $r : '', 1, 0, $align);
    }
};

$pdf->SetFont($pdf->FontStd, 'B', 14);
$pdf->Cell($W, 8, 'Fiche d\'inscription des équipes', 0, 1, 'L');
$pdf->Ln(2);

// Autant de tableaux que la page en contient, deux par ligne.
for ($y = $pdf->GetY(); $y + $th <= $limit; $y += $th + $vgap) {
    $table($L, $y);
    $table($L + $tw + $gap, $y);
}

$pdf->Output('Fiche inscription equipes.pdf', 'I');
