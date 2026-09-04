@php
    /**
     * How the student progressed through the selected session.
     *
     * Each row is a Madrassa placement the student actually held, earliest
     * first, with the class and section it was taught in. The rows come from
     * the enrollment records themselves, so a placement the student has
     * since been moved out of keeps its own class and section rather than
     * being overwritten by where they sit today.
     *
     * School placements cannot appear here, and neither can placements from
     * another session: the report builder is bounded by the Madrassa track
     * and by the selected session before this view sees anything.
     */
    $track = $report->trackRecord();

    // "Mar 2026 - Jun 2026". A placement still open runs to the end of the
    // session, because this table is scoped to that one session.
    $period = function (array $row) {
        $start = $row['start']?->format('M Y');
        $end = $row['period_end']?->format('M Y');

        if ($start === null) {
            return $end ?? '—';
        }

        return $end === null || $end === $start ? $start : $start.' – '.$end;
    };
@endphp

<h3>{{ $t('track_record') }}</h3>

@if($track === [])
    <p class="muted">{{ $t('no_track_record') }}</p>
@else
    <table>
        <thead>
            <tr>
                <th>{{ $t('period') }}</th>
                <th>{{ $t('department') }}</th>
                <th>{{ $t('track_class') }}</th>
                <th>{{ $t('section') }}</th>
                <th>{{ $t('status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($track as $row)
                <tr>
                    <td>{{ $period($row) }}</td>
                    <td>{{ $row['department'] ?? $t('na') }}</td>
                    <td class="bold">{{ $row['class'] ?? $t('na') }}</td>
                    <td>{{ $row['section'] ?? $t('no_section') }}</td>
                    <td>
                        {{-- A placement with no end date is still running. --}}
                        {{ $row['end'] === null ? $t('ongoing') : $row['status'] }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
