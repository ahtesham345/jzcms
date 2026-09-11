<x-layout.admin title="Student Terms Settings">
    <x-slot name="header">
        Settings &mdash; Student Terms / Instructions
    </x-slot>

    <div class="space-y-6">
        @include('settings.partials.tabs')

        @if(session('success'))
            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-sm text-green-800">{{ session('success') }}</p>
            </div>
        @endif

        @error('terms')
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-800">{{ $message }}</p>
            </div>
        @enderror

        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-800">Student Terms / Instructions</h2>
            <p class="text-sm text-gray-600 mt-1">
                The instructions a guardian reads and agrees to. Each department has its own set, so the
                madrassa and the school can ask for different things. They appear on the public admission
                form, on an application in Admission Management, and on the student's profile.
            </p>

            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex">
                    <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-blue-800">Students studying two programmes</p>
                        <p class="text-sm text-blue-800 mt-1">
                            A student type that covers two programmes &mdash; Hifz + School, or
                            Dars-e-Nizami + Computer &mdash; is shown both departments' instructions
                            together, madrassa first, with any line that appears in both shown once.
                            There is no separate set to write for the combination.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- What stays the same for everyone -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800">Shared wording</h3>
            <p class="text-sm text-gray-600 mt-1">
                The heading and the agreement sentence are the institution's own and are the same for every
                department, so they are not edited per department.
            </p>

            <dl class="mt-4 space-y-3">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Heading</dt>
                    <dd dir="rtl" lang="ur" class="text-right text-gray-800 leading-loose bg-gray-50 rounded-lg px-4 py-2">
                        {{ $heading }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Agreement</dt>
                    <dd dir="rtl" lang="ur" class="text-right text-gray-800 leading-loose bg-gray-50 rounded-lg px-4 py-2">
                        {{ $agreement }}
                    </dd>
                </div>
            </dl>
        </div>

        <form method="POST" action="{{ route('settings.student-terms.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            @foreach($departments as $departmentId => $entry)
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                {{ $entry['department']->name }}
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                One instruction per line. Blank lines are ignored.
                            </p>
                        </div>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $entry['configured'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                            {{ $entry['configured'] ? 'Configured' : 'Using defaults' }}
                        </span>
                    </div>

                    <div class="px-6 py-6">
                        <label for="terms_{{ $departmentId }}" class="sr-only">
                            {{ $entry['department']->name }} instructions
                        </label>
                        {{-- Right to left and in Urdu, because that is what is
                             being typed. The lines are the structure, so
                             nothing reflows them. --}}
                        <textarea
                            name="terms[{{ $departmentId }}]"
                            id="terms_{{ $departmentId }}"
                            rows="18"
                            dir="rtl"
                            lang="ur"
                            class="w-full px-4 py-3 text-right leading-loose border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('terms.'.$departmentId) border-red-500 @enderror"
                        >{{ old('terms.'.$departmentId, implode("\n", $entry['items'])) }}</textarea>
                        @error('terms.'.$departmentId)
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-gray-500">
                            {{ count($entry['items']) }} {{ \Illuminate\Support\Str::plural('instruction', count($entry['items'])) }} currently shown for this department.
                        </p>
                    </div>
                </div>
            @endforeach

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow-sm px-6 py-4 flex items-center justify-end gap-3">
                <a href="{{ route('settings.edit') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    Save Instructions
                </button>
            </div>
        </form>
    </div>
</x-layout.admin>
