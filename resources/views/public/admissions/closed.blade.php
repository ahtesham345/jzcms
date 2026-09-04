@php
    use App\Models\Setting;

    /**
     * The public admission page when the form may not be used.
     *
     * Three states, three messages. Which one is shown is decided on the
     * server by Setting::admissionFormState(); this template only words it.
     *
     * The dates are the configured ones, read back from the settings row.
     * Nothing here is hardcoded, and where no window has been configured no
     * date is printed rather than one being invented.
     */
    $message = match ($state) {
        Setting::ADMISSION_FORM_NOT_YET_OPEN => [
            'heading' => 'Admissions Are Not Open Yet',
            'body' => 'Online admission applications will open on '
                .$institution->admissionFormOpensAtLabel().'.',
        ],
        Setting::ADMISSION_FORM_CLOSED => [
            'heading' => 'Admissions Are Closed',
            'body' => 'The admission submission period ended on '
                .$institution->admissionFormClosesAtLabel().'.',
        ],
        default => [
            'heading' => 'Admissions Are Currently Closed',
            'body' => 'Online admission submissions are not currently available.',
        ],
    };
@endphp

<x-guest-layout title="Admissions">
    <div class="py-4 text-center">
        {{-- The icon carries the tone: waiting for a date that is coming,
             or a period that has ended. --}}
        @if($state === Setting::ADMISSION_FORM_NOT_YET_OPEN)
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        @else
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
        @endif

        <h2 class="mt-4 text-2xl font-bold text-gray-800">{{ $message['heading'] }}</h2>

        <p class="mt-2 text-gray-600">{{ $message['body'] }}</p>

        @if($state === Setting::ADMISSION_FORM_NOT_YET_OPEN && $institution->admissionFormClosesAtLabel())
            <p class="mt-1 text-sm text-gray-500">
                The form will remain open until {{ $institution->admissionFormClosesAtLabel() }}.
            </p>
        @endif

        <p class="mt-6 text-sm text-gray-500">
            Please contact the office if you need help with an admission.
        </p>

        @if($institution->phone_number)
            <p class="mt-1 text-sm font-medium text-gray-700">{{ $institution->phone_number }}</p>
        @endif
    </div>
</x-guest-layout>
