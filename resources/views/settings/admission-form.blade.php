@php
    use App\Models\Setting;

    // Populated from what was posted when a save came back with errors, and
    // from the stored window otherwise, so a rejected form does not throw
    // away what the administrator had typed.
    $opensAt = $settings->admission_form_opens_at;
    $closesAt = $settings->admission_form_closes_at;

    $opensDate = old('opens_date', $opensAt?->format('Y-m-d'));
    $opensTime = old('opens_time', $opensAt?->format('H:i'));
    $closesDate = old('closes_date', $closesAt?->format('Y-m-d'));
    $closesTime = old('closes_time', $closesAt?->format('H:i'));

    $enabled = (bool) old('admission_form_enabled', $settings->admission_form_enabled);

    $statusBanner = match ($state) {
        Setting::ADMISSION_FORM_OPEN => [
            'classes' => 'border-green-200 bg-green-50 text-green-800',
            'title' => 'The public admission form is open right now.',
            'detail' => 'Applications submitted online are being accepted until the closing time below.',
        ],
        Setting::ADMISSION_FORM_NOT_YET_OPEN => [
            'classes' => 'border-blue-200 bg-blue-50 text-blue-800',
            'title' => 'The public admission form has not opened yet.',
            'detail' => 'It will start accepting applications at the opening time below.',
        ],
        Setting::ADMISSION_FORM_CLOSED => [
            'classes' => 'border-amber-200 bg-amber-50 text-amber-800',
            'title' => 'The admission period has ended.',
            'detail' => 'The public form is closed. Set a new window and save to reopen it.',
        ],
        default => [
            'classes' => 'border-gray-200 bg-gray-50 text-gray-700',
            'title' => 'The public admission form is switched off.',
            'detail' => 'Nobody can submit an online application until it is enabled with a complete window.',
        ],
    };
@endphp

<x-layout.admin title="Admission Form Settings">
    <x-slot name="header">
        Settings — Admission Form
    </x-slot>

    <div class="space-y-6">
        @include('settings.partials.tabs')

        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-800">Admission Form Schedule</h2>
            <p class="text-sm text-gray-600 mt-1">
                When the public online admission form can be reached. Outside this window the public link shows
                a closed message instead of the form, and submissions are refused — including a form that was
                opened before the closing time and submitted after it.
            </p>

            {{-- What the settings currently amount to, said in words. The
                 same state the public page is deciding from, so this cannot
                 disagree with what a visitor sees. --}}
            <div class="mt-4 rounded-lg border p-4 {{ $statusBanner['classes'] }}">
                <p class="text-sm font-medium">{{ $statusBanner['title'] }}</p>
                <p class="text-sm mt-1">{{ $statusBanner['detail'] }}</p>
                @if($settings->admissionFormOpensAtLabel() && $settings->admissionFormClosesAtLabel())
                    <p class="text-sm mt-2">
                        Opens {{ $settings->admissionFormOpensAtLabel() }} &middot;
                        Closes {{ $settings->admissionFormClosesAtLabel() }}
                    </p>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('settings.admission-form.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- The toggle -->
            <div class="bg-white rounded-lg shadow-sm p-6 space-y-4">
                <div class="border-b pb-2">
                    <h3 class="text-lg font-semibold text-gray-800">Admission Form</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Switching this off closes the public form immediately, whatever window is saved below.
                        The window is kept, so it can be switched back on without entering it again.
                    </p>
                </div>

                <label for="admission_form_enabled" class="flex items-start cursor-pointer">
                    {{-- The unchecked box posts nothing, so a hidden field
                         carries the "off" value. The request reads the
                         checkbox as a boolean either way. --}}
                    <input type="hidden" name="admission_form_enabled" value="0">
                    <input
                        type="checkbox"
                        name="admission_form_enabled"
                        id="admission_form_enabled"
                        value="1"
                        {{ $enabled ? 'checked' : '' }}
                        class="mt-1 h-4 w-4 flex-shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="ml-3">
                        <span class="block text-sm font-medium text-gray-800">Admission Form Enabled</span>
                        <span class="block text-sm text-gray-600">
                            Allow the public admission form to be used during the window below.
                        </span>
                    </span>
                </label>
                <x-input-error :messages="$errors->get('admission_form_enabled')" class="mt-2" />
            </div>

            <!-- The window -->
            <div class="bg-white rounded-lg shadow-sm p-6 space-y-6">
                <div class="border-b pb-2">
                    <h3 class="text-lg font-semibold text-gray-800">Admission Window</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Both ends are inclusive: the form is open at exactly the opening time and still open at
                        exactly the closing time. A window may span several days.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="opens_date" class="block text-sm font-medium text-gray-700 mb-1">
                            Admission Opens — Date
                        </label>
                        <input type="date" name="opens_date" id="opens_date" value="{{ $opensDate }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('opens_date')" class="mt-2" />
                    </div>

                    <div>
                        <label for="opens_time" class="block text-sm font-medium text-gray-700 mb-1">
                            Admission Opens — Time
                        </label>
                        <input type="time" name="opens_time" id="opens_time" value="{{ $opensTime }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('opens_time')" class="mt-2" />
                    </div>

                    <div>
                        <label for="closes_date" class="block text-sm font-medium text-gray-700 mb-1">
                            Admission Closes — Date
                        </label>
                        <input type="date" name="closes_date" id="closes_date" value="{{ $closesDate }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('closes_date')" class="mt-2" />
                    </div>

                    <div>
                        <label for="closes_time" class="block text-sm font-medium text-gray-700 mb-1">
                            Admission Closes — Time
                        </label>
                        <input type="time" name="closes_time" id="closes_time" value="{{ $closesTime }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('closes_time')" class="mt-2" />
                    </div>
                </div>

                <p class="text-sm text-gray-500">
                    Times are in the application's timezone ({{ config('app.timezone') }}), which is the clock
                    the server uses to decide whether the form is open.
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Save Settings
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-layout.admin>
