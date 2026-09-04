{{--
    The paper for every madrassa report.

    Plain CSS, deliberately. This document is rendered by dompdf, not by a
    browser, so none of the application's Tailwind reaches it - and it must
    not: a PDF has no sidebar, no navbar, no buttons and nothing to click.
    What is here is what reaches the paper.

    Black on white throughout, with grey rules rather than fills, so the
    report prints legibly on a mono office printer.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 12mm 16mm 12mm;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            color: #000;
            margin: 0;
        }

        /* Fixed footer: dompdf repeats it on every page and resolves the
           page counters, which is where the page numbers come from. */
        .page-footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            height: 10mm;
            font-size: 8px;
            color: #444;
            border-top: 0.5pt solid #999;
            padding-top: 2mm;
        }

        .page-footer .left { float: left; }
        .page-footer .right { float: right; }
        .page-footer .right:after { content: counter(page) " / " counter(pages); }

        h1 { font-size: 15px; margin: 0 0 2mm 0; }
        h2 { font-size: 12px; margin: 0 0 1mm 0; }
        h3 {
            font-size: 10px;
            margin: 4mm 0 1.5mm 0;
            padding-bottom: 1mm;
            border-bottom: 0.75pt solid #333;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        p { margin: 0 0 1.5mm 0; }

        .doc-header {
            border-bottom: 1.25pt solid #000;
            padding-bottom: 3mm;
            margin-bottom: 4mm;
        }

        /* The institution letterhead. A borderless table so dompdf lays the
           logo beside the name without a float, and so the block costs the
           few millimetres it looks like rather than a page. */
        table.institution-bar {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 2mm 0;
        }

        table.institution-bar td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        td.institution-logo-cell {
            width: 18mm;
            padding-right: 3mm;
        }

        /* Bounded on both axes and fixed on neither, so the logo is scaled
           down to fit and never stretched. Measured: with a fixed height
           plus a max-width, dompdf squashes a wide logo to the box - a
           4000x800 mark came out 18mm x 14mm. With two maxima the same
           mark comes out 18mm x 3.6mm, which is its own shape. */
        img.institution-logo {
            max-height: 14mm;
            max-width: 18mm;
        }

        h1.institution-name { font-size: 15px; margin: 0; }
        p.institution-tagline { font-size: 9px; color: #444; margin: 0.5mm 0 0 0; }
        p.institution-address { font-size: 8px; color: #555; margin: 0.5mm 0 0 0; }

        .muted { color: #555; }
        .small { font-size: 8px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2mm;
        }

        th, td {
            border: 0.5pt solid #999;
            padding: 1.2mm 1.5mm;
            text-align: left;
            vertical-align: top;
        }

        thead th {
            background: #eee;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Repeat the head of a long register on each page it spills onto. */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        td.num, th.num { text-align: right; }

        /* The label/value grid used by the profile blocks. */
        table.facts td { border: none; padding: 0.8mm 2mm 0.8mm 0; }
        table.facts td.label { color: #555; width: 26%; }

        .badge {
            display: inline-block;
            border: 0.5pt solid #333;
            padding: 0.3mm 1.2mm;
            font-size: 8px;
        }

        .photo {
            width: 22mm;
            height: 22mm;
            border: 0.5pt solid #999;
            object-fit: cover;
        }

        .photo-placeholder {
            width: 22mm;
            height: 22mm;
            border: 0.5pt solid #999;
            text-align: center;
            font-size: 16px;
            line-height: 22mm;
            color: #666;
        }

        /* Each student starts on a fresh sheet in a multi-student report. */
        .student-block { page-break-after: always; }
        .student-block.last { page-break-after: auto; }

        .note {
            border: 0.5pt solid #999;
            background: #f4f4f4;
            padding: 1.5mm 2mm;
            margin-bottom: 2mm;
            font-size: 8px;
        }
    </style>
</head>
<body>
    {{-- The institution's own name, not the software's. Resolved through
         the memoised Setting::current(), which the letterhead in the
         content above has already read, so the footer costs no query of
         its own however many pages it repeats on.

         English: this document is rendered by dompdf, which performs no
         Arabic-script shaping - an Urdu name here would come out as
         disconnected letters. --}}
    <div class="page-footer">
        <span class="left">{{ \App\Models\Setting::current()->brandName(\App\Support\ResultReportLanguage::ENGLISH) }} — generated {{ $generatedAt->format('d M, Y H:i') }}</span>
        <span class="right">Page </span>
    </div>

    @yield('content')
</body>
</html>
