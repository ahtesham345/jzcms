<?php

namespace App\Support;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renders a report in Urdu.
 *
 * A second engine, and only for Urdu. The reason is a hard limitation
 * rather than a preference: dompdf draws the characters it is given in the
 * order it is given them, and Arabic-script text needs neither of those.
 * Urdu letters change shape by position - a kaf at the start of a word is
 * a different glyph from the same kaf at the end - and the line runs right
 * to left. dompdf performs no glyph shaping and has no bidirectional
 * layout, so an Urdu report rendered through it comes out as disconnected
 * letters in the wrong order, or as empty boxes when the font has no
 * Arabic script at all.
 *
 * mPDF implements the OpenType substitutions and the bidirectional
 * algorithm, and ships XB Riyaz, a font with the Arabic-script glyphs Urdu
 * needs. Measured on the word "کک": mPDF draws two different glyphs for the
 * two kafs, an initial form and a final one, which is the shaping actually
 * happening.
 *
 * Everything that already worked keeps working exactly as it did. The
 * English reports and the detailed student performance report still go
 * through PdfRenderer and dompdf; nothing in this class is on their path.
 * Both engines emit the same kind of response - an inline PDF the browser
 * opens in a tab - so the two are interchangeable from a controller's point
 * of view.
 */
class UrduPdfRenderer
{
    /**
     * Render a Blade view as an inline PDF, shaped for Urdu.
     *
     * @param  array<string, mixed>  $data
     */
    public static function inline(string $view, array $data, string $filename): Response
    {
        $html = View::make($view, $data)->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 16,
            // mPDF needs somewhere to spool font subsets and page buffers.
            // Kept inside storage rather than the system temp directory so
            // the application owns it on every host it runs on.
            'tempDir' => self::temporaryDirectory(),
            // Detect the script per run and pick a font that can draw it.
            // This is what puts the Urdu text in XB Riyaz while leaving the
            // Latin data - names, dates, marks - in the Latin font.
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        // The document's own direction. Set here rather than only in CSS so
        // the bidirectional algorithm has it before the first line is laid
        // out, which is what keeps a Latin name inside an Urdu row from
        // dragging the rest of the line around it.
        $mpdf->SetDirectionality('rtl');

        $mpdf->WriteHTML($html);

        $pdf = $mpdf->Output('', Destination::STRING_RETURN);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            // inline, never attachment: a report opens in a browser tab and
            // is never pushed at the user as a download. The same contract
            // PdfRenderer keeps.
            'Content-Disposition' => 'inline; filename="'.self::safeFilename($filename).'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Get the directory mPDF spools into, creating it when it is missing.
     */
    private static function temporaryDirectory(): string
    {
        $path = storage_path('app/mpdf');

        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * Reduce a filename to something safe to put in a header.
     *
     * Student names reach this, and a quote or a newline in one would let
     * stored data write its own Content-Disposition.
     */
    private static function safeFilename(string $filename): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? 'report';

        $safe = trim($safe, '-');

        return $safe === '' ? 'report.pdf' : $safe;
    }
}
