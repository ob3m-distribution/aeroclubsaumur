<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bon-cadeau.php';

/* ==================================================================
   Générateur de PDF « bon cadeau » sans librairie externe.
   Utilise le visuel officiel (assets/img/bon-cadeau-recto-v2.jpg) en
   fond pleine page, avec une bande personnalisée (n°, vol, montant,
   validité, bénéficiaire). Repli texte si l'image est absente.
   ================================================================== */

/** Échappe une chaîne pour un littéral PDF et la convertit en WinAnsi. */
function pdf_txt(string $s): string
{
    $s = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
    if ($s === false) $s = '';
    return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => '']);
}

/** Largeur approx. d'un texte (pour centrer). */
function pdf_larg(string $t, float $taille): float { return strlen($t) * $taille * 0.5; }

/** Construit le PDF du bon cadeau et retourne ses octets bruts. */
function pdf_bon_cadeau(array $bon): string
{
    // Version « baseline » dédiée au PDF (DCTDecode ne gère pas partout le JPEG progressif).
    foreach (['bon-cadeau-pdf.jpg', 'bon-cadeau-recto-v2.jpg'] as $nom) {
        $img = __DIR__ . '/../assets/img/' . $nom;
        $sz = is_file($img) ? @getimagesize($img) : false;
        if ($sz && ($sz[2] ?? 0) === IMAGETYPE_JPEG) {
            return pdf_bon_visuel($bon, $img, (int) $sz[0], (int) $sz[1]);
        }
    }
    return pdf_bon_simple($bon);
}

/** Rendu avec le visuel officiel en fond. */
function pdf_bon_visuel(array $bon, string $imgPath, int $iw, int $ih): string
{
    $numero  = (string) ($bon['numero_bon'] ?: $bon['reference']);
    $montant = prix((int) $bon['montant_cents']);
    $vol     = libelle_vol_bon($bon);
    $fin     = date('d/m/Y', strtotime((string) ($bon['date_fin_validite'] ?: $bon['expire_le'] ?: 'now')) ?: time());
    $offert  = trim((string) ($bon['offert_a'] ?? ''));

    $W = 595.0; $H = 842.0;                 // A4 portrait, points
    // Image ajustée à la hauteur (voucher entier visible), centrée horizontalement.
    $imgW = $H * ($iw / $ih);
    $imgX = ($W - $imgW) / 2;

    $cx = $W / 2;
    // Bande translucide au-dessus des pictogrammes, sur le tarmac.
    $by = 120.0; $bh = 86.0;

    $flux  = "q\n" . sprintf("%.2f 0 0 %.2f %.2f 0 cm /Im1 Do\n", $imgW, $H, $imgX) . "Q\n";
    // Ruban marine translucide.
    $flux .= "/GS1 gs 0.055 0.145 0.290 rg 0 " . $by . " 595 " . $bh . " re f /GS0 gs\n";
    // Filets dorés fins en haut et bas du ruban.
    $flux .= "0.706 0.565 0.180 rg 0 " . ($by + $bh - 2) . " 595 2 re f 0 " . $by . " 595 2 re f\n";

    // Textes centrés sur le ruban.
    $t1 = pdf_txt('BON N° ' . $numero);
    $t2 = pdf_txt($vol . '   ·   ' . $montant);
    $t3 = pdf_txt('Valable jusqu\'au ' . $fin . ($offert !== '' ? '   ·   Offert à ' . $offert : ''));

    $flux .= sprintf("BT /F2 16 Tf 0.945 0.855 0.560 rg %.1f %.1f Td (%s) Tj ET\n", $cx - pdf_larg($t1, 16) / 2, $by + $bh - 26, $t1);
    $flux .= sprintf("BT /F1 10.5 Tf 1 1 1 rg %.1f %.1f Td (%s) Tj ET\n", $cx - pdf_larg($t2, 10.5) / 2, $by + $bh - 46, $t2);
    $flux .= sprintf("BT /F1 9 Tf 0.90 0.92 0.96 rg %.1f %.1f Td (%s) Tj ET\n", $cx - pdf_larg($t3, 9) / 2, $by + 16, $t3);

    $jpeg = (string) file_get_contents($imgPath);

    $obj = [];
    $obj[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $obj[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $obj[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << "
            . "/Font << /F1 4 0 R /F2 5 0 R >> /XObject << /Im1 7 0 R >> "
            . "/ExtGState << /GS0 8 0 R /GS1 9 0 R >> >> /Contents 6 0 R >>";
    $obj[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    $obj[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
    $obj[6] = "<< /Length " . strlen($flux) . " >>\nstream\n" . $flux . "\nendstream";
    $obj[7] = "<< /Type /XObject /Subtype /Image /Width $iw /Height $ih /ColorSpace /DeviceRGB "
            . "/BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
    $obj[8] = "<< /Type /ExtGState /ca 1 >>";
    $obj[9] = "<< /Type /ExtGState /ca 0.74 >>";

    return pdf_assembler($obj);
}

/** Assemble les objets PDF avec table xref. */
function pdf_assembler(array $obj): string
{
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ($obj as $n => $corps) {
        $offsets[$n] = strlen($pdf);
        $pdf .= $n . " 0 obj\n" . $corps . "\nendobj\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
    foreach ($obj as $n => $_) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
    }
    $pdf .= "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";
    return $pdf;
}

/** Repli sobre (sans image) : bandeau marine + cadre. */
function pdf_bon_simple(array $bon): string
{
    $numero  = (string) ($bon['numero_bon'] ?: $bon['reference']);
    $montant = prix((int) $bon['montant_cents']);
    $vol     = libelle_vol_bon($bon);
    $fin     = date('d/m/Y', strtotime((string) ($bon['date_fin_validite'] ?: $bon['expire_le'] ?: 'now')) ?: time());
    $offert  = trim((string) ($bon['offert_a'] ?? ''));
    $cx = 297.5;
    $centre = fn(string $t, float $s): float => $cx - pdf_larg(pdf_txt($t), $s) / 2;

    $flux  = "0.078 0.161 0.302 rg 0 752 595 90 re f\n0.690 0.553 0.173 rg 0 746 595 6 re f\n";
    $flux .= sprintf("1 1 1 rg BT /F2 24 Tf %.1f 800 Td (%s) Tj ET\n", $centre(CLUB['nom'], 24), pdf_txt(mb_strtoupper(CLUB['nom'])));
    $lignes = [['F2', 38, $centre('BON CADEAU', 38), 672, 'BON CADEAU'], ['F1', 15, $centre($vol, 15), 636, $vol]];
    $y = 570;
    $infos = [['N° du bon', $numero], ['Montant', $montant], ['Valable jusqu\'au', $fin]];
    if ($offert !== '') $infos[] = ['Offert à', $offert];
    foreach ($infos as [$lib, $v]) { $lignes[] = ['F1', 13, 130, $y, $lib . ' :']; $lignes[] = ['F2', 15, 320, $y, $v]; $y -= 36; }
    $flux .= "0.078 0.161 0.302 rg\n";
    foreach ($lignes as [$p, $t, $x, $yy, $tx]) $flux .= sprintf("BT /%s %d Tf %.1f %d Td (%s) Tj ET\n", $p, $t, $x, $yy, pdf_txt($tx));
    $flux .= "0.690 0.553 0.173 RG 1.5 w 45 95 505 625 re S\n";

    $obj = [];
    $obj[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $obj[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $obj[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
    $obj[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    $obj[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
    $obj[6] = "<< /Length " . strlen($flux) . " >>\nstream\n" . $flux . "\nendstream";
    return pdf_assembler($obj);
}
