@extends('results.pdf.short-layout', ['title' => $t('report_title')])

@section('content')
    @php
        $termLabels = [
            \App\Models\StudentResult::TERM_FIRST => $t('first_term'),
            \App\Models\StudentResult::TERM_FINAL => $t('final_term'),
            \App\Http\Controllers\Admin\StudentResultReportController::ALL_TERMS => $t('all_terms'),
        ];
    @endphp

    <div class="doc-header">
        {{-- The shared letterhead: logo, institution name, tagline and -
             on this document, because it is the copy an office hands to a
             parent - the address. All four in the report's own language,
             each falling back to its English twin when the Urdu one has
             not been entered. --}}
        @include('results.pdf.partials.institution', [
            'language' => $language,
            'showAddress' => true,
        ])

        <h2>{{ $t('report_title') }} — {{ $testType }}</h2>
        <p class="small muted">
            {{ $heading['session']?->name ?? $t('all_sessions') }}
            &middot; {{ $termLabels[$termLabel] ?? $termLabel }}
            @if($heading['department']) &middot; {{ $heading['department']->name }} @endif
            @if($heading['academicClass']) &middot; {{ $heading['academicClass']->name }} @endif
            @if($heading['section']) &middot; {{ $heading['section']->name }} @endif
            @if($heading['student']) &middot; {{ $heading['student']->full_name }} @endif
            @if($heading['search'] ?? null) &middot; {{ $t('search') }} "{{ $heading['search'] }}" @endif
        </p>
        <p class="small muted">{{ $t('madrassa_only') }}</p>
    </div>

    @if($capped)
        <div class="note">{{ $t('capped_note') }}</div>
    @endif

    @forelse($reports as $report)
        {{-- One controlled break between students and none after the last,
             so a group report never ends on a blank sheet. Nothing inside a
             student's own sections breaks: the four of them are meant to sit
             together on one page. --}}
        <div class="student-report">
            @include('results.pdf.partials.short-student', ['report' => $report])

            {{-- The result itself, which is what this document exists for.
                 The percentage and grade are the stored ones: nothing here
                 divides a mark or re-grades a paper. --}}
            @include('results.pdf.partials.short-results', [
                'report' => $report,
                'terms' => $terms,
                'testType' => $testType,
            ])

            @include('results.pdf.partials.short-summary', ['report' => $report])

            {{-- How the student progressed through the session: the
                 placements they held, earliest first. Replaces the single
                 "current position" line the summary used to carry. --}}
            @include('results.pdf.partials.short-track-record', ['report' => $report])

            {{-- The session month by month. Prayer is left out on purpose:
                 this is the short, result-focused document, and the prayer
                 register has its own section in the detailed report. --}}
            @include('results.pdf.partials.short-months', ['report' => $report])
        </div>
    @empty
        <p>{{ $t('no_students') }}</p>
    @endforelse
@endsection
