@php
    /**
     * The madrassa progress summary.
     *
     * Read from the daily records the Hifz & Quran module already keeps.
     * "Prepared" is a day the lesson was written down; "unprepared" is a
     * recorded day it was not. There is no prepared/unprepared column in
     * madrassa_daily_records, so the report says what the data says and no
     * more - nothing here scores a student.
     *
     * Nothing is added up either. The quantities are free text - "1 page",
     * "half page", "1/2 para" - and this module has never been entitled to
     * turn them into numbers.
     */
    $progress = $report->progressSummary();
    $latest = $report->latestRecord();
@endphp

<h3>Madrassa Progress — {{ $progress['record_type'] ?? 'No programme record' }}</h3>

@if($progress['record_type'] === null)
    <p class="muted">No daily academic record is kept for this student's programme.</p>
@else
    <table>
        <thead>
            <tr>
                <th class="num">Recorded Days</th>
                <th class="num">Prepared {{ $progress['lesson_label'] }}</th>
                <th class="num">Unprepared {{ $progress['lesson_label'] }}</th>
                <th class="num">Unprepared {{ $progress['revision_label'] }}</th>
                <th class="num">Manzil Days</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">{{ $progress['recorded_days'] }}</td>
                <td class="num">{{ $progress['prepared_lessons'] }}</td>
                <td class="num">{{ $progress['unprepared_lessons'] }}</td>
                <td class="num">{{ $progress['unprepared_revision'] }}</td>
                <td class="num">{{ $progress['manzil'] }}</td>
            </tr>
        </tbody>
    </table>

    <table class="facts" style="margin-top: 2mm;">
        <tr>
            <td class="label">Latest Recorded Date</td>
            <td>{{ $latest?->record_date?->format('d M, Y') ?? 'None recorded' }}</td>
            <td class="label">Latest Teacher</td>
            <td>{{ $latest?->teacher?->full_name ?? 'Not recorded' }}</td>
        </tr>
        <tr>
            <td class="label">Latest Lesson</td>
            {{-- The record's own summary line, exactly as the teacher
                 wrote it. --}}
            <td colspan="3">{{ $latest?->workSummary() ?? 'No work recorded' }}</td>
        </tr>
        <tr>
            <td class="label">Current Position</td>
            <td colspan="3">
                @if($latest)
                    @php($highlights = $latest->workHighlights())
                    @forelse($highlights as $label => $value)
                        <span class="badge">{{ $label }}: {{ $value }}</span>
                    @empty
                        <span class="muted">Not recorded</span>
                    @endforelse
                @else
                    <span class="muted">Not recorded</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Recorded Under</td>
            <td colspan="3">
                {{-- The placement the latest record was written against,
                     not the student's placement today. --}}
                {{ $latest?->studentAcademicEnrollment?->academicSession?->name ?? 'N/A' }}
                &middot; {{ $latest?->studentAcademicEnrollment?->academicClass?->name ?? 'N/A' }}
                @if($latest?->studentAcademicEnrollment?->section)
                    / {{ $latest->studentAcademicEnrollment->section->name }}
                @endif
            </td>
        </tr>
    </table>
@endif
