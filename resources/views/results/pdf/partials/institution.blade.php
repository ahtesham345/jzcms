@php
    /**
     * The institution's letterhead, shared by every generated report.
     *
     * One partial rather than the same markup copied into each document, so
     * a change to the institution's branding reaches all of them and none
     * of them can drift.
     *
     * It resolves the settings itself instead of being handed them. That is
     * deliberate: Setting::current() is memoised on the container for the
     * life of the request, so however many reports, layouts and headers ask
     * for it the table is read once - and no PDF controller has to grow a
     * branding parameter it would otherwise have no use for.
     *
     * The language is the document's, passed in by the layout that already
     * knows it. This partial never decides a language: ResultReportLanguage
     * remains the authority, and the Urdu name, tagline and address each
     * fall back to their English twin when they have not been entered.
     *
     * The logo is embedded as a data URI. dompdf runs with remote content
     * disabled and chrooted, so a report cannot link to a file - it has to
     * carry it - and mPDF reads the same URI, which is why the Urdu report
     * needs nothing of its own here.
     *
     * $showAddress is off by default. A parent's result slip wants the
     * address on it; a long internal register does not need it eating the
     * top of every document.
     */
    $institution = \App\Models\Setting::current();
    $institutionLanguage = $language ?? \App\Support\ResultReportLanguage::ENGLISH;

    $institutionLogo = $institution->logoDataUri();
    $institutionTagline = trim($institution->taglineLine($institutionLanguage));
    $institutionAddress = trim($institution->addressLine($institutionLanguage));

    $showAddress = $showAddress ?? false;
@endphp

{{-- A borderless table, not floats. dompdf mishandles floated blocks
     inside the flow of a long report - that is what once turned this
     document into eleven pages - and mPDF lays a table out the same way in
     both directions, so one structure serves both engines.

     Stacked rather than side by side: the logo heads the sheet on its own
     row and the name follows underneath it, both centred by text-align on
     the cell. text-align is the one centring instruction both engines
     honour reliably - dompdf has no flexbox, and margin:auto does not
     centre an inline image - so the letterhead is built out of it rather
     than out of anything either renderer would ignore. --}}
<table class="institution-bar">
    @if($institutionLogo)
        <tr>
            {{-- The logo's own row, with the gap to the name below carried
                 by the cell's padding rather than by a margin on the image:
                 dompdf drops vertical margins on an inline image, and
                 padding on the cell it sits in it does honour. --}}
            <td class="institution-logo-cell">
                {{-- Bounded on both axes and fixed on neither, so the logo
                     is scaled down into the box and never stretched. --}}
                <img src="{{ $institutionLogo }}" alt="" class="institution-logo">
            </td>
        </tr>
    @endif
    <tr>
        <td class="institution-text-cell">
            <h1 class="institution-name">{{ $institution->brandName($institutionLanguage) }}</h1>
            @if($institutionTagline !== '')
                <p class="institution-tagline">{{ $institutionTagline }}</p>
            @endif
            @if($showAddress && $institutionAddress !== '')
                <p class="institution-address">{{ $institutionAddress }}</p>
            @endif
        </td>
    </tr>
</table>
