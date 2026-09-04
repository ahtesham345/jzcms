@php
    /**
     * The Grand Test result section of the short report.
     *
     * The short report's own copy, so its labels can be translated without
     * touching the partial the detailed report shares.
     *
     * One row per term in scope. The two terms are reported separately and
     * are never averaged or added together.
     *
     * The percentage and grade are the stored ones. Nothing here divides one
     * mark by another: the application computed both on save, and this only
     * reads them.
     */
    $byTerm = $report->resultsByTerm();

    // The stored term and status are English keys; the report prints them in
    // the reader's language. The value in the database is untouched.
    $termLabels = [
        \App\Models\StudentResult::TERM_FIRST => $t('first_term'),
        \App\Models\StudentResult::TERM_FINAL => $t('final_term'),
    ];

    $statusLabels = [
        'Passed' => $t('passed'),
        'Failed' => $t('failed'),
        'Not Entered' => $t('not_entered'),
    ];
@endphp

<h3>{{ $t('result') }}</h3>

<table>
    <thead>
        <tr>
            <th>{{ $t('term') }}</th>
            <th>{{ $t('test') }}</th>
            <th class="num">{{ $t('total_marks') }}</th>
            <th class="num">{{ $t('obtained_marks') }}</th>
            <th class="num">{{ $t('percentage') }}</th>
            <th>{{ $t('grade') }}</th>
            <th>{{ $t('status') }}</th>
            <th>{{ $t('result_date') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($terms as $term)
            @php($result = $byTerm[$term] ?? null)
            @php($status = \App\Support\MadrassaStudentReport::statusFor($result))
            <tr>
                <td class="bold">{{ $termLabels[$term] ?? $term }}</td>
                <td>{{ $testType }}</td>
                @if($result)
                    <td class="num">{{ number_format((float) $result->total_marks, 2) }}</td>
                    <td class="num">{{ number_format((float) $result->obtained_marks, 2) }}</td>
                    <td class="num">{{ $result->formattedPercentage() }}</td>
                    <td>{{ $result->grade }}</td>
                    <td>{{ $statusLabels[$status] ?? $status }}</td>
                    <td>{{ $result->result_date?->format('d M, Y') ?? '—' }}</td>
                @else
                    <td class="num">-</td>
                    <td class="num">-</td>
                    <td class="num">-</td>
                    <td>-</td>
                    <td>{{ $statusLabels[$status] ?? $status }}</td>
                    <td>-</td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
