@php
    /**
     * The paper for the short result report, in either language.
     *
     * Two engines render this one file. English goes through dompdf, which
     * is what every report in the project has always used; Urdu goes
     * through mPDF, because dompdf performs no Arabic-script shaping and no
     * bidirectional layout. The markup is shared so the two languages
     * cannot drift into different documents - only the footer differs, and
     * only because the two engines repeat a footer by different means.
     *
     * The detailed student performance report keeps the original shared
     * layout and is deliberately untouched.
     *
     * A note on the fixed footer: floats inside a `position: fixed` block
     * leak into dompdf's flow and push every following table down a page at
     * a time. That cost this report eleven pages once. The two halves sit in
     * a borderless table instead - never floats.
     */
    $rtl = ($direction ?? 'ltr') === 'rtl';
@endphp
<!DOCTYPE html>
<html lang="{{ $language ?? 'en' }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @if(! $rtl)
        {{-- dompdf takes the page box from CSS. mPDF is given the same
             margins through its constructor instead: handed both, it adds
             them together, which collapses the usable height and spreads a
             one-page report over hundreds of pages. --}}
        @page {
            size: A4 portrait;
            margin: 14mm 12mm 16mm 12mm;
        }
        @endif

        body {
            font-family: {!! $fontFamily ?? '"DejaVu Sans", sans-serif' !!};
            font-size: 12px;
            line-height: 1.25;
            color: #000;
            margin: 0;
            direction: {{ $direction ?? 'ltr' }};
        }

        h1 { font-size: 18px; margin: 0 0 1.5mm 0; }
        h2 { font-size: 14px; margin: 0 0 1mm 0; }
        h3 {
            font-size: 13px;
            margin: 2mm 0 1mm 0;
            padding-bottom: 1mm;
            border-bottom: 0.75pt solid #333;
            @if(! $rtl)
            {{-- Urdu has no letter case, and an uppercase transform over
                 Arabic script is only work for the renderer to undo. --}}
            text-transform: uppercase;
            letter-spacing: 0.3px;
            @endif
        }

        p { margin: 0 0 1mm 0; }

        .doc-header {
            border-bottom: 1.25pt solid #000;
            padding-bottom: 1.5mm;
            margin-bottom: 2mm;
        }

        /* The institution letterhead. A borderless table rather than
           floats: dompdf pushes floated blocks down a page at a time in a
           long report, and mPDF lays a table out identically in both
           directions, so one structure serves both engines and both
           languages. */
        table.institution-bar {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 1.5mm 0;
        }

        table.institution-bar td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        td.institution-logo-cell {
            width: 18mm;
            /* The gutter follows the reading direction, so the logo sits
               beside the name rather than jammed against it in Urdu. */
            padding-{{ $rtl ? 'left' : 'right' }}: 3mm;
        }

        /* Bounded on both axes and fixed on neither, so the logo is scaled
           down to fit and never stretched. Measured: with a fixed height
           plus a max-width, dompdf squashes a wide logo to the box - a
           4000x800 mark came out 18mm x 14mm. With two maxima the same
           mark comes out 18mm x 3.6mm, which is its own shape. */
        img.institution-logo {
            max-height: 15mm;
            max-width: 18mm;
        }

        h1.institution-name { font-size: 18px; margin: 0; }
        p.institution-tagline { font-size: 11px; color: #444; margin: 0.5mm 0 0 0; }
        p.institution-address { font-size: 10px; color: #555; margin: 0.5mm 0 0 0; }

        .muted { color: #555; }
        .small { font-size: 10px; }
        .bold { font-weight: bold; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.2mm;
        }

        th, td {
            border: 0.5pt solid #999;
            padding: 1.2mm 1.5mm;
            text-align: {{ $rtl ? 'right' : 'left' }};
            vertical-align: top;
            font-size: 12px;
        }

        thead th {
            background: #eee;
            font-size: 10.5px;
            @if(! $rtl)
            text-transform: uppercase;
            letter-spacing: 0.2px;
            @endif
        }

        /* Repeat the head of a table on each page it spills onto, and keep a
           row whole rather than splitting it across the fold. */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        td.num, th.num { text-align: {{ $rtl ? 'left' : 'right' }}; }

        /* The label/value grid. Fixed layout with a stated label width: left
           to compute its own columns, dompdf makes a six-cell row far wider
           than the page and the row then wraps into something very tall. */
        table.facts { table-layout: fixed; }
        table.facts td {
            border: none;
            padding: 0.8mm 2mm 0.8mm 0;
            font-size: 12px;
        }
        table.facts td.label { color: #555; width: 14%; }

        .badge {
            display: inline-block;
            border: 0.5pt solid #333;
            padding: 0.4mm 1.4mm;
            font-size: 10.5px;
        }

        /* One controlled break between students, and none after the last.
           Nothing inside a student's own sections breaks: the report is
           meant to be read as one compact block. */
        .student-report + .student-report { page-break-before: always; }

        .note {
            border: 0.5pt solid #999;
            background: #f4f4f4;
            padding: 1.5mm 2mm;
            margin-bottom: 2mm;
            font-size: 10px;
        }

        .page-footer {
            position: {{ $rtl ? 'static' : 'fixed' }};
            bottom: -10mm;
            left: 0;
            right: 0;
            height: 10mm;
            font-size: 9.5px;
            color: #444;
            border-top: 0.5pt solid #999;
            padding-top: 2mm;
        }

        table.footer-bar {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        table.footer-bar td {
            border: none;
            padding: 0;
            font-size: 9.5px;
            color: #444;
        }

        table.footer-bar td.page-number { text-align: {{ $rtl ? 'left' : 'right' }}; }
        @if(! $rtl)
        {{-- dompdf resolves the CSS page counters inside the fixed footer.
             mPDF does not implement them - it would resolve this to an
             empty generated box and then trip over the empty chunk - so the
             Urdu footer uses mPDF's own {PAGENO} placeholders instead. --}}
        table.footer-bar td.page-number:after { content: counter(page) " / " counter(pages); }
        @endif
    </style>
</head>
<body>
    @if($rtl)
        {{-- mPDF repeats a footer through its own tag rather than through a
             fixed block, and resolves the page numbers from its own
             placeholders. --}}
        <htmlpagefooter name="reportfooter">
            <table class="footer-bar">
                <tr>
                    {{-- The institution's own name in the document's
                         language. mPDF shapes Arabic script, so the Urdu
                         name is safe here in a way it is not on the dompdf
                         side. --}}
                    <td>{{ \App\Models\Setting::current()->brandName($language ?? null) }} — {{ $t('generated') }} {{ $generatedAt->format('d M, Y H:i') }}</td>
                    <td style="text-align: left;">{{ $t('page') }} {PAGENO} / {nbpg}</td>
                </tr>
            </table>
        </htmlpagefooter>
        <sethtmlpagefooter name="reportfooter" value="on" />
    @else
        <div class="page-footer">
            <table class="footer-bar">
                <tr>
                    <td>{{ \App\Models\Setting::current()->brandName($language ?? null) }} — {{ $t('generated') }} {{ $generatedAt->format('d M, Y H:i') }}</td>
                    <td class="page-number">{{ $t('page') }} </td>
                </tr>
            </table>
        </div>
    @endif

    @yield('content')
</body>
</html>
