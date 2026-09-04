@php
    /**
     * The month-wise table for the reporting session.
     *
     * Every month the session covers gets a row. The figures are bounded
     * three ways - by the session, by the student's own enrollment dates,
     * and by today when the session is still running - so a month still to
     * come reports nothing rather than a year of absences, and a student
     * who joined in October has no September.
     */
    $months = $report->months();
    $percent = fn ($value) => $value === null ? 'N/A' : number_format($value, 2) . '%';
@endphp

<h3>Month-wise Madrassa Performance</h3>

@if($months === [])
    <p class="muted">No academic session is selected, so there are no months to report.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Total Days</th>
                <th class="num">Present</th>
                <th class="num">Absent</th>
                <th class="num">Attendance %</th>
                <th class="num">Prepared</th>
                <th class="num">Unprepared</th>
                <th class="num">Unprep. Sabqi</th>
                <th class="num">Manzil</th>
                @if($showPrayer ?? false)
                    <th class="num">Prayer %</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($months as $month)
                <tr>
                    <td>{{ $month['label'] }}</td>
                    <td class="num">{{ $month['working_days'] }}</td>
                    <td class="num">{{ $month['present'] }}</td>
                    <td class="num">{{ $month['absent'] }}</td>
                    <td class="num">{{ $percent($month['attendance_percentage']) }}</td>
                    <td class="num">{{ $month['prepared_lessons'] }}</td>
                    <td class="num">{{ $month['unprepared_lessons'] }}</td>
                    <td class="num">{{ $month['unprepared_revision'] }}</td>
                    <td class="num">{{ $month['manzil'] }}</td>
                    @if($showPrayer ?? false)
                        <td class="num">{{ $percent($month['prayer_percentage']) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="small muted">
        Total Days counts the teaching days this student was enrolled for, weekends excluded. A session still
        running is counted only as far as today, so months still to come report nothing.
    </p>
@endif
