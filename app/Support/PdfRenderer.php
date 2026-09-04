<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Renders a Blade view to a PDF the browser opens in a tab.
 *
 * One place, so every report in the project produces the same paper: A4
 * portrait, the same margins, the same fonts. The report views say what is
 * on the page; this says what the page is.
 *
 * The response is served inline rather than as an attachment. That is the
 * whole behavioural requirement - clicking a report link opens the PDF in a
 * new tab instead of dropping a file in the downloads folder - and it is a
 * property of this one header, so it cannot drift per report.
 *
 * Remote content is deliberately disabled. A report is assembled from the
 * project's own data, and a PDF renderer that fetches URLs is a request
 * forgery waiting to happen. Student photos are embedded as data URIs by
 * photoDataUri() below instead, which needs no network and no filesystem
 * access from inside the renderer.
 */
class PdfRenderer
{
    /**
     * Render a Blade view as an inline PDF response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function inline(string $view, array $data, string $filename): Response
    {
        $html = View::make($view, $data)->render();

        $options = new Options;
        // Bundled fonts only, and no remote fetching of any kind: nothing
        // in a report should be able to make the server issue a request.
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        // The report views contain no PHP and no inline scripts. Turning
        // this off means a stray <script> in stored data cannot run inside
        // the renderer either.
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', 'portrait');
        $options->set('chroot', [public_path()]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            // inline, never attachment. This is what opens the report in a
            // browser tab rather than forcing a download.
            'Content-Disposition' => 'inline; filename="'.self::safeFilename($filename).'"',
            // A report reflects the data at the moment it was asked for.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Read a stored photo as a data URI, or null when there is none.
     *
     * Embedded rather than linked because the renderer has no network and
     * no business reading arbitrary paths. A missing or unreadable file
     * yields null, which the views draw as an initial - a report must never
     * fail to render because a photo was deleted.
     */
    public static function photoDataUri(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        try {
            $contents = $disk->get($path);
        } catch (\Throwable) {
            return null;
        }

        if ($contents === null || $contents === '') {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($contents);
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
