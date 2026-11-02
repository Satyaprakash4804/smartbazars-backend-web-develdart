<?php

namespace App\Support\SmartBazars;

use Dompdf\Dompdf;
use Dompdf\Options;

/** Render a Blade view to PDF bytes with the bundled dompdf. */
class Pdf
{
    public static function fromView(string $view, array $data = [], string|array $paper = 'A4', string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', [public_path(), base_path('packages/smartbazars-libs')]);
        $options->set('tempDir', storage_path('app'));
        $options->set('fontCache', storage_path('app'));
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $pdf->setPaper($paper, $orientation);
        $pdf->render();
        return $pdf->output();
    }
}
