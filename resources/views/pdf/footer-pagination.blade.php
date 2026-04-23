{{-- Paginazione a piè di pagina per DomPDF. Richiede enable_php = true. --}}
<script type="text/php">
if (isset($pdf)) {
    $pdf->page_script('
        $text = "Pag. " . $PAGE_NUM . " di " . $PAGE_COUNT;
        $font = $fontMetrics->getFont("DejaVu Sans", "normal");
        $size = 9;
        $color = [0.4, 0.4, 0.4];
        $y = $pdf->get_height() - 20;
        $width = $fontMetrics->getTextWidth($text, $font, $size);
        $x = ($pdf->get_width() - $width) / 2;
        $pdf->text($x, $y, $text, $font, $size, $color);
    ');
}
</script>
