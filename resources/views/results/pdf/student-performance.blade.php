@extends('results.pdf.layout', ['title' => 'Madrassa Student Detailed Report'])

@section('content')
    <div class="doc-header">
        {{-- The shared letterhead. English only, and not by oversight: this
             document is rendered by dompdf, which performs no Arabic-script
             shaping, so an Urdu institution name would print as
             disconnected letters. The Urdu report is the short result
             report, which goes through mPDF.

             No address: this is a long internal register rather than
             something handed over a counter. --}}
        @include('results.pdf.partials.institution', [
            'language' => \App\Support\ResultReportLanguage::ENGLISH,
        ])

        <h2>Student Detailed Report — Madrassa Track Record</h2>
        <p class="small muted">
            {{ $student->full_name }}
            &middot; {{ $student->registration_number }}
            &middot; {{ $session?->name ?? 'No session' }}
        </p>
        <p class="small muted">
            Madrassa records only. School enrollments, registers and results are not reported here.
        </p>
    </div>

    @include('results.pdf.partials.student', ['report' => $report])

    <h3>Madrassa Enrollment History</h3>
    <table>
        <thead>
            <tr>
                <th>Session</th>
                <th>Department</th>
                <th>Class</th>
                <th>Section</th>
                <th>Start</th>
                <th>End</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            {{-- Each placement as it was, newest first. This is the history
                 every historical figure in the rest of the document is read
                 against. --}}
            @foreach($report->enrollments() as $enrollment)
                <tr>
                    <td>{{ $enrollment->academicSession?->name ?? 'N/A' }}</td>
                    <td>{{ $enrollment->department?->name ?? 'N/A' }}</td>
                    <td>{{ $enrollment->academicClass?->name ?? 'N/A' }}</td>
                    <td>{{ $enrollment->section?->name ?? 'No section' }}</td>
                    <td>{{ $enrollment->start_date?->format('d M, Y') ?? '—' }}</td>
                    <td>{{ $enrollment->end_date?->format('d M, Y') ?? 'Open' }}</td>
                    <td>{{ $enrollment->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('results.pdf.partials.attendance', ['report' => $report])

    @include('results.pdf.partials.progress', ['report' => $report])

    @include('results.pdf.partials.prayer', ['report' => $report])

    @include('results.pdf.partials.months', ['report' => $report, 'showPrayer' => true])

    <h3>Result History</h3>
    <table>
        <thead>
            <tr>
                <th>Term</th>
                <th>Test</th>
                <th>Session</th>
                <th>Class</th>
                <th>Section</th>
                <th class="num">Total Marks</th>
                <th class="num">Obtained Marks</th>
                <th class="num">Percentage</th>
                <th>Grade</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            {{-- First Term and Final Term stay separate rows and are never
                 combined. The session, class and section come from each
                 result's own enrollment. --}}
            @forelse($resultHistory as $result)
                @php($resultEnrollment = $result->studentAcademicEnrollment)
                <tr>
                    <td>{{ $result->term }}</td>
                    <td>{{ $result->test_type }}</td>
                    <td>{{ $resultEnrollment?->academicSession?->name ?? 'N/A' }}</td>
                    <td>{{ $resultEnrollment?->academicClass?->name ?? 'N/A' }}</td>
                    <td>{{ $resultEnrollment?->section?->name ?? 'No section' }}</td>
                    <td class="num">{{ number_format((float) $result->total_marks, 2) }}</td>
                    <td class="num">{{ number_format((float) $result->obtained_marks, 2) }}</td>
                    <td class="num">{{ $result->formattedPercentage() }}</td>
                    <td>{{ $result->grade }}</td>
                    <td>{{ \App\Support\MadrassaStudentReport::statusFor($result) }}</td>
                    <td>{{ $result->result_date?->format('d M, Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="muted">No results recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Daily Madrassa Record History</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Session</th>
                <th>Department</th>
                <th>Class</th>
                <th>Section</th>
                <th>Sabaq</th>
                <th>Sabaq Qty</th>
                <th>Sabqi</th>
                <th>Sabqi Qty</th>
                <th>Manzil</th>
                <th>Manzil Qty</th>
                <th>Tomorrow</th>
                <th>Teacher</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            {{-- The placement columns come from each record's own enrollment,
                 so a day recorded before a promotion still names the class it
                 was recorded in. --}}
            @forelse($dailyRecords as $record)
                @php($recordEnrollment = $record->studentAcademicEnrollment)
                <tr>
                    <td>{{ $record->record_date?->format('d M, Y') ?? '—' }}</td>
                    <td>{{ $recordEnrollment?->academicSession?->name ?? 'N/A' }}</td>
                    <td>{{ $recordEnrollment?->department?->name ?? 'N/A' }}</td>
                    <td>{{ $recordEnrollment?->academicClass?->name ?? 'N/A' }}</td>
                    <td>{{ $recordEnrollment?->section?->name ?? 'No section' }}</td>
                    <td>{{ $record->sabaq ?: '—' }}</td>
                    <td>{{ $record->sabaq_quantity ?: '—' }}</td>
                    <td>{{ $record->sabqi ?: '—' }}</td>
                    <td>{{ $record->sabqi_quantity ?: '—' }}</td>
                    <td>{{ $record->manzil ?: '—' }}</td>
                    <td>{{ $record->manzil_quantity ?: '—' }}</td>
                    <td>{{ $record->next_sabaq ?: '—' }}</td>
                    <td>{{ $record->teacher?->full_name ?? '—' }}</td>
                    <td>{{ $record->remarks ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="14" class="muted">No daily records for this session.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($dailyRecords->count() >= $historyLimit)
        <p class="small muted">Showing the most recent {{ $historyLimit }} daily records.</p>
    @endif

    <h3>Prayer History</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Fajr</th>
                <th>Zuhr</th>
                <th>Asr</th>
                <th>Maghrib</th>
                <th>Isha</th>
            </tr>
        </thead>
        <tbody>
            {{-- A weekend reads OFF, never Absent: the institution does not
                 sit, so nobody missed a prayer. A prayer with no row reads
                 Unmarked, which is the office being behind rather than the
                 student being away. --}}
            @forelse($prayerHistory as $day)
                <tr>
                    <td>{{ $day['date']->format('d M, Y') }}{{ $day['off_day'] ? ' (' . $day['off_day'] . ')' : '' }}</td>
                    @foreach($day['prayers'] as $status)
                        <td>{{ $status }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No prayer records.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Academic Attendance History</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Period</th>
                <th>Class</th>
                <th>Section</th>
                <th>Status</th>
                <th>Absence Reason</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendanceHistory as $mark)
                @php($markEnrollment = $mark->studentAcademicEnrollment)
                <tr>
                    <td>{{ $mark->attendance_date?->format('d M, Y') ?? '—' }}</td>
                    <td>{{ $mark->attendance_period }}</td>
                    <td>{{ $markEnrollment?->academicClass?->name ?? 'N/A' }}</td>
                    <td>{{ $markEnrollment?->section?->name ?? 'No section' }}</td>
                    <td>{{ $mark->status }}</td>
                    <td>{{ $mark->absence_reason ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No attendance records.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($attendanceHistory->count() >= $historyLimit)
        <p class="small muted">Showing the most recent {{ $historyLimit }} attendance marks.</p>
    @endif
@endsection
