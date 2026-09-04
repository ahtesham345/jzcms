@php
    /**
     * The compact attendance and progress line of the short report.
     *
     * Both halves come from the existing registers through
     * MadrassaStudentReport - the academic attendance module for the days,
     * the Hifz daily record for the lessons. Nothing here counts anything of
     * its own, and nothing is added up: the Quran quantities are free text
     * and this module has never been entitled to turn them into numbers.
     *
     * "Prepared" is a day the lesson was written down; "unprepared" is a
     * recorded day it was not. That is what the data says and no more.
     */
    $attendance = $report->attendanceSummary();
    $progress = $report->progressSummary();
    $latest = $report->latestRecord();
@endphp

<h3>{{ $t('attendance_progress') }}</h3>

<table>
    <thead>
        <tr>
            <th class="num">{{ $t('working_days') }}</th>
            <th class="num">{{ $t('present_days') }}</th>
            <th class="num">{{ $t('absent_days') }}</th>
            <th class="num">{{ $t('attendance_percentage') }}</th>
            <th class="num">{{ $t('recorded_days') }}</th>
            <th class="num">{{ $t('prepared_lessons') }}</th>
            <th class="num">{{ $t('unprepared_lessons') }}</th>
            <th class="num">{{ $t('unprepared_sabqi') }}</th>
            <th class="num">{{ $t('manzil_days') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="num">{{ $attendance['working_days'] }}</td>
            <td class="num">{{ $attendance['present'] }}</td>
            <td class="num">{{ $attendance['absent'] }}</td>
            <td class="num bold">{{ $attendance['percentage'] === null ? $t('na') : number_format($attendance['percentage'], 2).'%' }}</td>
            <td class="num">{{ $progress['recorded_days'] }}</td>
            <td class="num">{{ $progress['prepared_lessons'] }}</td>
            <td class="num">{{ $progress['unprepared_lessons'] }}</td>
            <td class="num">{{ $progress['unprepared_revision'] }}</td>
            <td class="num">{{ $progress['manzil'] }}</td>
        </tr>
    </tbody>
</table>

<table class="facts">
    <tr>
        {{-- The student's position in the session is the Track Record
             section below, which shows the placements they moved through
             rather than only where they stand today. --}}
        <td class="label">{{ $t('latest_record') }}</td>
        <td>{{ $latest?->record_date?->format('d M, Y') ?? $t('none') }}</td>
    </tr>
</table>

<p class="small muted">{{ $t('attendance_note') }}</p>
