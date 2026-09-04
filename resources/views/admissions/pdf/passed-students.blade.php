@extends('results.pdf.short-layout', ['title' => $t('passed_students_title')])

@section('content')
    <div class="doc-header">
        {{-- The shared letterhead: logo, institution name, tagline and the
             address. This sheet is pinned up in public, so it carries the
             address the way a parent's copy of a result does. --}}
        @include('results.pdf.partials.institution', [
            'language' => $language,
            'showAddress' => true,
        ])

        <h2>{{ $t('passed_students_title') }}</h2>
        {{-- The session the query was actually scoped to, stated in full
             rather than dropped in among the other filters: this sheet is
             about one academic year and says which. --}}
        <p class="bold">
            {{ $t('academic_session') }}: {{ $heading['session']?->name ?? $t('na') }}
        </p>
        @php
            // Joined rather than each part carrying its own separator, so an
            // absent filter cannot leave a stray divider behind it.
            $headingParts = array_values(array_filter([
                $heading['department']?->name,
                $heading['academicClass']?->name,
                $heading['studentType'],
                $heading['gender'],
                $heading['search'] ? $t('search').' "'.$heading['search'].'"' : null,
                $t('generated_on').' '.$generatedAt->format('d M, Y'),
            ]));
        @endphp
        <p class="small muted">{!! implode(' &middot; ', array_map('e', $headingParts)) !!}</p>
        <p class="small muted">{{ $t('passed_students_note') }}</p>
    </div>

    @if($capped)
        {{-- Page one, where somebody standing at the board reads it, rather
             than beside the total on the last page. It carries both numbers
             so the sheet is never mistaken for the complete list. --}}
        <div class="note">
            {{ strtr($t('passed_capped_note'), [
                ':shown' => number_format($applications->count()),
                ':total' => number_format($total),
            ]) }}
        </div>
    @endif

    @if($session === null)
        {{-- Nothing was scoped to, so nothing is listed. Said explicitly, so
             an empty sheet is not mistaken for "nobody passed this year". --}}
        <p class="bold">{{ $t('no_academic_session') }}</p>
    @elseif($applications->isEmpty())
        {{-- The notice is still a notice when nobody has passed yet: it says
             so on the board rather than going up as a blank sheet. --}}
        <p class="bold">{{ $t('no_passed_students') }}</p>
    @else
        <table>
            <thead>
                {{-- Eight columns on A4 portrait: the fixed widths below
                     come to 117mm of the 186mm the page has, leaving the two
                     name columns about 34mm each. Names wrap rather than
                     being cut off. --}}
                <tr>
                    <th class="num" style="width: 9mm;">{{ $t('sr_no') }}</th>
                    <th style="width: 26mm;">{{ $t('application_no') }}</th>
                    <th>{{ $t('student_name') }}</th>
                    <th>{{ $t('father_name') }}</th>
                    <th style="width: 22mm;">{{ $t('department') }}</th>
                    <th style="width: 22mm;">{{ $t('class') }}</th>
                    <th style="width: 16mm;">{{ $t('test_result') }}</th>
                    <th style="width: 22mm;">{{ $t('admission_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($applications as $application)
                    <tr>
                        <td class="num">{{ $loop->iteration }}</td>
                        <td>{{ $application->application_number }}</td>
                        <td>{{ $application->student_name }}</td>
                        <td>{{ $application->father_name }}</td>
                        {{-- An application entered before the class selection
                             existed carries neither, so the cell says so
                             rather than inventing a placement. --}}
                        <td>{{ $application->departmentLabel() ?? $t('na') }}</td>
                        <td>{{ $application->classLabel() ?? $t('na') }}</td>
                        {{-- Only passed applicants reach this document, so
                             the column is a constant. It is printed anyway:
                             the sheet has to say what it is a list of to
                             somebody reading it off a wall. --}}
                        <td class="bold">{{ $t('result_passed') }}</td>
                        {{-- Whether the admission itself has been approved,
                             which is a separate question from the test and is
                             the reason this sheet lists both. It is shown, and
                             it filters nothing: every passed candidate in the
                             session is here whether or not a seat has been
                             given to them yet. --}}
                        <td>{{ $t($application->admissionStatusKey()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- The true number these filters match, which is not the number of
             rows above it whenever the notice has been capped. --}}
        <p class="small muted">{{ $t('total_passed') }}: {{ number_format($total) }}</p>
    @endif
@endsection
