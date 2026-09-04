<x-layout.admin title="Settings">
    <x-slot name="header">
        Settings
    </x-slot>

    <div class="space-y-6">
        @include('settings.partials.tabs')

        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-800">Institution &amp; System Settings</h2>
            <p class="text-sm text-gray-600 mt-1">
                One global record for the whole application. These are the institution's own details and the
                system-wide preferences reports and documents fall back to.
            </p>
            @unless($isConfigured)
                {{-- The first visit. Said plainly rather than left for the
                     administrator to work out from an empty form: nothing
                     has been saved yet, and what is on screen is a
                     starting point. --}}
                <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm text-blue-800">
                        No settings have been saved yet. The form below is filled in with sensible defaults —
                        review them and save to set the institution up.
                    </p>
                </div>
            @endunless
        </div>

        {{-- enctype is required for the logo: without it the browser posts
             the file name as text and the upload silently never arrives.
             The method is spoofed to PUT because a file upload has to be a
             real POST. --}}
        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Institution Information -->
            <div class="bg-white rounded-lg shadow-sm p-6 space-y-6">
                <div class="border-b pb-2">
                    <h3 class="text-lg font-semibold text-gray-800">Institution Information</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        The Urdu fields are optional. Where one is left empty, the English value is used instead.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="institution_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Institution Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="institution_name" id="institution_name" required maxlength="255"
                               value="{{ old('institution_name', $settings->institution_name) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('institution_name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="institution_name_urdu" class="block text-sm font-medium text-gray-700 mb-1">
                            Institution Name (Urdu)
                        </label>
                        {{-- dir="rtl" on each Urdu input rather than on the
                             page: only these fields are right to left. --}}
                        <input type="text" name="institution_name_urdu" id="institution_name_urdu" dir="rtl" maxlength="255"
                               value="{{ old('institution_name_urdu', $settings->institution_name_urdu) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('institution_name_urdu')" class="mt-2" />
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">
                            Address <span class="text-red-500">*</span>
                        </label>
                        <textarea name="address" id="address" rows="3" required maxlength="1000"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address', $settings->address) }}</textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>

                    <div>
                        <label for="address_urdu" class="block text-sm font-medium text-gray-700 mb-1">Address (Urdu)</label>
                        <textarea name="address_urdu" id="address_urdu" rows="3" dir="rtl" maxlength="1000"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address_urdu', $settings->address_urdu) }}</textarea>
                        <x-input-error :messages="$errors->get('address_urdu')" class="mt-2" />
                    </div>

                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">
                            Phone Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="phone_number" id="phone_number" required maxlength="50"
                               value="{{ old('phone_number', $settings->phone_number) }}"
                               placeholder="e.g. 03001234567"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="email" maxlength="255"
                               value="{{ old('email', $settings->email) }}"
                               placeholder="e.g. info@example.edu.pk"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <label for="website" class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                        <input type="url" name="website" id="website" maxlength="255"
                               value="{{ old('website', $settings->website) }}"
                               placeholder="e.g. https://example.edu.pk"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-sm text-gray-500">Include https:// so the address can be linked.</p>
                        <x-input-error :messages="$errors->get('website')" class="mt-2" />
                    </div>

                    <div>
                        <label for="principal_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Principal / Imam Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="principal_name" id="principal_name" required maxlength="255"
                               value="{{ old('principal_name', $settings->principal_name) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('principal_name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="principal_name_urdu" class="block text-sm font-medium text-gray-700 mb-1">
                            Principal / Imam Name (Urdu)
                        </label>
                        <input type="text" name="principal_name_urdu" id="principal_name_urdu" dir="rtl" maxlength="255"
                               value="{{ old('principal_name_urdu', $settings->principal_name_urdu) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('principal_name_urdu')" class="mt-2" />
                    </div>

                    <div>
                        <label for="tagline" class="block text-sm font-medium text-gray-700 mb-1">Tagline</label>
                        <input type="text" name="tagline" id="tagline" maxlength="255"
                               value="{{ old('tagline', $settings->tagline) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('tagline')" class="mt-2" />
                    </div>

                    <div>
                        <label for="tagline_urdu" class="block text-sm font-medium text-gray-700 mb-1">Tagline (Urdu)</label>
                        <input type="text" name="tagline_urdu" id="tagline_urdu" dir="rtl" maxlength="255"
                               value="{{ old('tagline_urdu', $settings->tagline_urdu) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get('tagline_urdu')" class="mt-2" />
                    </div>
                </div>

                <!-- Logo -->
                <div class="border-t border-gray-200 pt-6">
                    <label for="logo" class="block text-sm font-medium text-gray-700 mb-1">Institution Logo</label>

                    <div class="flex flex-col sm:flex-row sm:items-start gap-6">
                        {{-- The logo as it stands. hasLogo() checks the file
                             is still on the disk, so a path whose file has
                             gone reads as "no logo" instead of rendering a
                             broken image. --}}
                        <div class="flex-shrink-0">
                            <p class="text-sm font-medium text-gray-500 mb-2">Current Logo</p>
                            <div class="h-24 w-24 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden">
                                @if($settings->hasLogo())
                                    <img src="{{ $settings->logoUrl() }}" alt="{{ $settings->institution_name }}" class="h-24 w-24 object-contain">
                                @else
                                    <span class="text-xs text-gray-400 text-center px-2">No logo</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex-1">
                            <input type="file" name="logo" id="logo" accept="image/jpeg,image/png"
                                   class="w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="mt-2 text-sm text-gray-500">
                                JPG, JPEG or PNG, up to 2MB.
                                @if($settings->hasLogo())
                                    Uploading a new logo replaces the current one; leaving this empty keeps it.
                                @endif
                            </p>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Preferences -->
            <div class="bg-white rounded-lg shadow-sm p-6 space-y-6">
                <div class="border-b pb-2">
                    <h3 class="text-lg font-semibold text-gray-800">System Preferences</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Application-wide defaults. Existing pages and reports are unchanged by this chunk — these are
                        stored so later work has one place to read them from.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="default_language" class="block text-sm font-medium text-gray-700 mb-1">
                            Default Language <span class="text-red-500">*</span>
                        </label>
                        <select name="default_language" id="default_language" required
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @foreach($languages as $code => $name)
                                <option value="{{ $code }}" {{ old('default_language', $settings->default_language) === $code ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        {{-- Said explicitly: this is a default, not a
                             switch. The PDF language selector on the
                             Results pages still decides each document. --}}
                        <p class="mt-1 text-sm text-gray-500">
                            The default for reports and documents. Each report can still be printed in either language.
                        </p>
                        <x-input-error :messages="$errors->get('default_language')" class="mt-2" />
                    </div>

                    <div>
                        <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">
                            Timezone <span class="text-red-500">*</span>
                        </label>
                        <select name="timezone" id="timezone" required
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @foreach($timezones as $identifier)
                                <option value="{{ $identifier }}" {{ old('timezone', $settings->timezone) === $identifier ? 'selected' : '' }}>
                                    {{ $identifier }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                    </div>

                    <div>
                        <label for="date_format" class="block text-sm font-medium text-gray-700 mb-1">
                            Date Format <span class="text-red-500">*</span>
                        </label>
                        <select name="date_format" id="date_format" required
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            {{-- Each option is labelled with today's date in
                                 that format, so the choice shows what it
                                 actually produces rather than a format
                                 string to decode. --}}
                            @foreach($dateFormats as $format => $example)
                                <option value="{{ $format }}" {{ old('date_format', $settings->date_format) === $format ? 'selected' : '' }}>
                                    {{ $example }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('date_format')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        {{ $isConfigured ? 'Save Changes' : 'Save Settings' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-layout.admin>
