@php
    /**
     * The academic attendance summary for the reporting window.
     *
     * Madrassa registers only. Present and Absent count register marks
     * rather than days, because the madrassa sits three registers a day and
     * that is the unit every attendance report in this project counts and
     * divides. Working Days is the calendar figure the marks sit inside, so
     * the two reconcile.
     */
    $attendance = $report->attendanceSummary();
@endphp

<h3>Academic Attendance</h3>

<table>
    <thead>
        <tr>
            <th class="num">Working Days</th>
            <th class="num">Registers Possible</th>
            <th class="num">Present</th>
            <th class="num">Absent</th>
            <th class="num">Recorded</th>
            <th class="num">Attendance %</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="num">{{ $attendance['working_days'] }}</td>
            <td class="num">{{ $attendance['opportunities'] }}</td>
            <td class="num">{{ $attendance['present'] }}</td>
            <td class="num">{{ $attendance['absent'] }}</td>
            <td class="num">{{ $attendance['recorded'] }}</td>
            <td class="num bold">{{ $attendance['percentage'] === null ? 'N/A' : number_format($attendance['percentage'], 2) . '%' }}</td>
        </tr>
    </tbody>
</table>

<p class="small muted">
    Madrassa registers only; School attendance is not counted. The madrassa sits three registers a day, so
    Present and Absent count register marks. The percentage is Present over what has actually been recorded,
    never over the calendar, so a month still to be transcribed is not read as absence.
</p>
