@php
    /**
     * The prayer register summary.
     *
     * The arithmetic is the Prayer Attendance module's, unchanged: the
     * denominator is the rows on file, so a prayer nobody has transcribed
     * is unrecorded rather than an absence, and a weekend never appears in
     * either column because no row can exist for one.
     */
    $prayer = $report->prayerSummary();
    $percent = fn ($value) => $value === null ? 'N/A' : number_format($value, 2) . '%';
@endphp

<h3>Prayer Attendance</h3>

<table>
    <thead>
        <tr>
            <th>Prayer</th>
            <th class="num">Present</th>
            <th class="num">Absent</th>
            <th class="num">Recorded</th>
            <th class="num">Percentage</th>
        </tr>
    </thead>
    <tbody>
        @foreach($prayer['prayers'] as $name => $counts)
            <tr>
                <td>{{ $name }}</td>
                <td class="num">{{ $counts['present'] }}</td>
                <td class="num">{{ $counts['absent'] }}</td>
                <td class="num">{{ $counts['recorded'] }}</td>
                <td class="num">{{ $percent($counts['percentage']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="bold">Overall</td>
            <td class="num bold">{{ $prayer['present'] }}</td>
            <td class="num bold">{{ $prayer['absent'] }}</td>
            <td class="num bold">{{ $prayer['recorded'] }}</td>
            <td class="num bold">{{ $percent($prayer['percentage']) }}</td>
        </tr>
    </tbody>
</table>

<p class="small muted">
    Total prayer records: {{ $prayer['recorded'] }} of {{ $prayer['expected'] }} possible for the working days
    this student was enrolled. A prayer with no row has not been transcribed yet and is not counted as an absence.
</p>
