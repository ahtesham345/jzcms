@php
    /**
     * The month-wise table of the short report.
     *
     * The short report's own copy, so its labels can be translated without
     * touching the partial the detailed report shares. The figures are the
     * same ones MadrassaStudentReport already produces: nothing here counts
     * anything of its own.
     *
     * Every month the session covers gets a row. The figures are bounded
     * three ways - by the session, by the student's own enrollment dates,
     * and by today when the session is still running - so a month still to
     * come reports nothing rather than a year of absences.
     */
    $months = $report->months();
    $percent = fn ($value) => $value === null ? $t('na') : number_format($value, 2).'%';
@endphp

<h3>{{ $t('monthly_report') }}</h3>

@if($months === [])
    <p class="muted">{{ $t('no_session_note') }}</p>
@else
    <table>
        <thead>
            <tr>
                <th>{{ $t('month') }}</th>
                <th class="num">{{ $t('total_days') }}</th>
                <th class="num">{{ $t('present') }}</th>
                <th class="num">{{ $t('absent') }}</th>
                <th class="num">{{ $t('attendance_percentage') }}</th>
                <th class="num">{{ $t('prepared') }}</th>
                <th class="num">{{ $t('unprepared') }}</th>
                <th class="num">{{ $t('unprepared_sabqi') }}</th>
                <th class="num">{{ $t('manzil') }}</th>
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
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="small muted">{{ $t('month_note') }}</p>
@endif
