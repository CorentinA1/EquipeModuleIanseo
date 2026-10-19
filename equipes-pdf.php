<?php
/**
 * equipes-pdf.php — classement des équipes libres (PDF, en-tête et pied des
 * impressions d'Ianseo).
 *
 * Un classement par conditions de tir (arme, distances, blason) et par sexe :
 * rang, équipe, score, 10, 9, puis ses archers (en gras : les 3 meilleurs).
 */
require_once __DIR__ . '/config.php';
require_once 'Common/pdf/ResultPDF.inc.php';

$entries = Equipe_entries();
$groups = Equipe_free_ranking(Equipe_free_read(), $entries);
$started = Equipe_state()['started'];

$pdf = new ResultPDF('Classement des équipes');
$W = $pdf->GetPageWidth() - 2 * $pdf->getSideMargin();
$limit = $pdf->GetPageHeight() - 22; // au-dessus du pied de page d'Ianseo
$cols = [['Rang', 14, 'C'], ['Équipe / archers', 0, 'L'], ['Classe', 18, 'C'], ['Score', 18, 'R'], ['10', 14, 'R'], ['9', 14, 'R']];
$cols[1][1] = $W - array_sum(array_column($cols, 1));

$head = function () use ($pdf, $cols) {
    $pdf->SetFillColor(225, 228, 235);
    $pdf->SetFont($pdf->FontStd, 'B', 8);
    foreach ($cols as $i => [$label, $w]) $pdf->Cell($w, 6, $label, 1, $i === count($cols) - 1 ? 1 : 0, 'C', 1);
    $pdf->SetDefaultColor();
};
$row = function (array $values, $bold, $h, $border = 'LR') use ($pdf, $cols) {
    foreach ($cols as $i => [, $w, $align]) {
        $pdf->SetFont($pdf->FontStd, $bold[$i] ?? false ? 'B' : '', $i === 1 && !($bold['team'] ?? false) ? 8 : 9);
        $pdf->Cell($w, $h, (string) ($values[$i] ?? ''), $border, $i === count($cols) - 1 ? 1 : 0, $align);
    }
};

if (!$groups) {
    $pdf->SetFont($pdf->FontStd, '', 11);
    $pdf->Cell($W, 8, 'Aucune équipe libre.', 0, 1);
}
foreach ($groups as $g) {
    if ($pdf->GetY() + 30 > $limit) $pdf->AddPage();
    $pdf->SetFillColor(0, 43, 146);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont($pdf->FontStd, 'B', 11);
    $pdf->Cell($W, 7, $g['label'] . '  (' . implode(', ', $g['classes']) . ')', 1, 1, 'L', 1);
    $pdf->SetDefaultColor();
    $head();
    foreach ($g['teams'] as $t) {
        $h = 6 + 4.5 * count($t['members']);
        if ($pdf->GetY() + $h > $limit) {
            $pdf->AddPage();
            $head();
        }
        $row([$t['rank'] ?? '-', $t['name'], '', $started && $t['score'] !== null ? $t['score'] : '',
            $started && $t['score'] !== null ? $t['gold'] : '', $started && $t['score'] !== null ? $t['xnine'] : ''],
            [0 => true, 1 => true, 3 => true, 'team' => true], 6, 'LTR');
        foreach ($t['members'] as $id) {
            $e = $entries[$id];
            $in = $started && in_array($id, $t['retained'], true);
            $row(['', '      ' . Equipe_person($e), $e['cls'], $started ? $e['score'] : '', $started ? $e['gold'] : '', $started ? $e['xnine'] : ''],
                [1 => $in, 3 => $in], 4.5);
        }
        $pdf->Cell($W, 0, '', 'T', 1);
    }
    $pdf->Ln(4);
}
$pdf->Output('Classement des equipes.pdf', 'I');
