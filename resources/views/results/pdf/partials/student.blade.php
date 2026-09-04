@php
    /**
     * The student block every report opens with.
     *
     * The placement shown here is the student's current madrassa one. Every
     * historical figure elsewhere in these reports reads its own row's
     * enrollment instead, which is what keeps an old result showing the
     * class it was sat in.
     *
     * The photo is embedded as a data URI rather than linked: the renderer
     * has no network, and a deleted file must not stop a report printing.
     */
    $student = $report->student();
    $current = $report->currentEnrollment();
    $photo = \App\Support\PdfRenderer::photoDataUri($student->photo);
@endphp

<table style="margin-bottom: 3mm;">
    <tr>
        <td style="border: none; width: 26mm; padding: 0;">
            @if($photo)
                <img class="photo" src="{{ $photo }}" alt="">
            @else
                <div class="photo-placeholder">{{ mb_strtoupper(mb_substr($student->full_name, 0, 1)) }}</div>
            @endif
        </td>
        <td style="border: none; padding: 0;">
            <table class="facts">
                <tr>
                    <td class="label">Student</td>
                    <td class="bold">{{ $student->full_name }}</td>
                    <td class="label">Registration No.</td>
                    <td>{{ $student->registration_number }}</td>
                </tr>
                <tr>
                    <td class="label">Father Name</td>
                    <td>{{ $student->father_name ?: '—' }}</td>
                    <td class="label">Roll No.</td>
                    <td>{{ $student->roll_number ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Programme</td>
                    <td>{{ $student->student_type }}</td>
                    <td class="label">Session</td>
                    <td>{{ $current?->academicSession?->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Department</td>
                    <td>{{ $current?->department?->name ?? 'N/A' }}</td>
                    <td class="label">Class</td>
                    <td>{{ $current?->academicClass?->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Section</td>
                    <td>{{ $current?->section?->name ?? 'No section' }}</td>
                    <td class="label">Track</td>
                    <td>{{ $current?->academic_track ?? 'Madrassa' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
