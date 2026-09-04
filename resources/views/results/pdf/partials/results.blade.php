@php
    /**
     * The Grand Test result section.
     *
     * One row per term in scope. The two terms are reported separately and
     * are never averaged or added together.
     *
     * The percentage and grade are the stored ones. Nothing here divides
     * one mark by another: the application computed both on save, and this
     * only reads them.
     */
    $byTerm = $report->resultsByTerm();
@endphp

<h3>{{ $testType }} Result</h3>

<table>
    <thead>
        <tr>
            <th>Term</th>
            <th>Test</th>
            <th class="num">Total Marks</th>
            <th class="num">Obtained Marks</th>
            <th class="num">Percentage</th>
            <th>Grade</th>
            <th>Status</th>
            <th>Result Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($terms as $term)
            @php($result = $byTerm[$term] ?? null)
            <tr>
                <td class="bold">{{ $term }}</td>
                <td>{{ $testType }}</td>
                @if($result)
                    <td class="num">{{ number_format((float) $result->total_marks, 2) }}</td>
                    <td class="num">{{ number_format((float) $result->obtained_marks, 2) }}</td>
                    <td class="num">{{ $result->formattedPercentage() }}</td>
                    <td>{{ $result->grade }}</td>
                    <td>{{ \App\Support\MadrassaStudentReport::statusFor($result) }}</td>
                    <td>{{ $result->result_date?->format('d M, Y') ?? '—' }}</td>
                @else
                    <td class="num">-</td>
                    <td class="num">-</td>
                    <td class="num">-</td>
                    <td>-</td>
                    <td>Not Entered</td>
                    <td>-</td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
