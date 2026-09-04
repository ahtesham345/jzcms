@php
    /**
     * The compact student block the short result report opens with.
     *
     * Deliberately lighter than the detailed report's block: no photo and no
     * father's name in the grid, because this document is about a result
     * rather than about the student's whole file.
     *
     * The placement shown is the student's current madrassa one. The result
     * rows below read their own enrollment instead, so a result recorded
     * before a promotion still names the class it was sat in.
     */
    $student = $report->student();
    $current = $report->currentEnrollment();
@endphp

<h3>{{ $t('student_information') }}</h3>

<table class="facts" style="margin-bottom: 2mm;">
    <tr>
        <td class="label">{{ $t('student') }}</td>
        <td class="bold">{{ $student->full_name }}</td>
        <td class="label">{{ $t('registration_no') }}</td>
        <td>{{ $student->registration_number }}</td>
        <td class="label">{{ $t('roll_no') }}</td>
        <td>{{ $student->roll_number ?: '—' }}</td>
    </tr>
    <tr>
        <td class="label">{{ $t('session') }}</td>
        <td>{{ $current?->academicSession?->name ?? $t('na') }}</td>
        <td class="label">{{ $t('department') }}</td>
        <td>{{ $current?->department?->name ?? $t('na') }}</td>
        <td class="label">{{ $t('programme') }}</td>
        <td>{{ $student->student_type }}</td>
    </tr>
    <tr>
        <td class="label">{{ $t('class') }}</td>
        <td>{{ $current?->academicClass?->name ?? $t('na') }}</td>
        <td class="label">{{ $t('section') }}</td>
        <td>{{ $current?->section?->name ?? '—' }}</td>
        <td class="label">{{ $t('track') }}</td>
        <td>{{ $current?->academic_track ?? 'Madrassa' }}</td>
    </tr>
</table>
