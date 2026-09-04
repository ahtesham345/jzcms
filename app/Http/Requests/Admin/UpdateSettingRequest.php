<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use App\Support\ResultReportLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saves the institution's global settings.
 *
 * The same rules serve the first save and every save after it, because the
 * Settings page is one form either way. There is no id in the request and
 * none in the route: which row this writes is the controller's business,
 * not the browser's.
 *
 * The logo is validated here but not returned by validated() as something
 * that can be assigned - it is an uploaded file, and the controller turns it
 * into a stored path before anything reaches the column.
 *
 * The three preference fields are checked against the lists the application
 * itself publishes rather than against arrays restated here, so widening the
 * languages, the date formats or PHP's timezone database widens this rule
 * with them and nothing can be stored that the application cannot then read.
 */
class UpdateSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The controller asks the same question through the project's existing
     * permission gate before this class is reached, and the route is behind
     * auth. This class is about what the request may say.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reduce blank optional text to null before anything is checked.
     *
     * An untouched input arrives as an empty string. Stored as '' it would
     * later read as "an Urdu name has been entered" and print an empty
     * heading on a report instead of falling back to the English one.
     */
    protected function prepareForValidation(): void
    {
        $optional = [
            'institution_name_urdu',
            'address_urdu',
            'email',
            'website',
            'principal_name_urdu',
            'tagline',
            'tagline_urdu',
        ];

        foreach ($optional as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value) === '' ? null : trim($value)]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['required', 'string', 'max:255'],
            'institution_name_urdu' => ['nullable', 'string', 'max:255'],

            // The same image rules the student and parent photos use, so
            // there is one answer in this project to "what may be
            // uploaded". SVG is not among them on purpose: it is a script
            // container as much as an image.
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],

            'address' => ['required', 'string', 'max:1000'],
            'address_urdu' => ['nullable', 'string', 'max:1000'],

            // Reasonable rather than rigid. Pakistani numbers are written
            // as 03001234567, +92 300 1234567 and (021) 111-222-333 by
            // turns, and refusing any of those would be wrong; letters and
            // free text are what this is actually keeping out.
            'phone_number' => ['required', 'string', 'max:50', 'regex:/^[0-9+()\-\s]+$/'],

            'email' => ['nullable', 'email', 'max:255'],

            // Requires a scheme, which is what makes the stored value safe
            // to put in an href later without guessing at one.
            'website' => ['nullable', 'url', 'max:255'],

            'principal_name' => ['required', 'string', 'max:255'],
            'principal_name_urdu' => ['nullable', 'string', 'max:255'],

            'tagline' => ['nullable', 'string', 'max:255'],
            'tagline_urdu' => ['nullable', 'string', 'max:255'],

            // The report language system's own codes. Anything else is
            // refused rather than quietly normalised to English: a
            // preference the administrator did not choose is worse than an
            // error message.
            'default_language' => ['required', Rule::in(ResultReportLanguage::LANGUAGES)],

            'timezone' => ['required', Rule::in(Setting::timezoneOptions())],

            'date_format' => ['required', Rule::in(Setting::DATE_FORMATS)],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institution_name.required' => 'Enter the name of the institution.',
            'logo.image' => 'The logo must be an image file.',
            'logo.mimes' => 'The logo must be a JPG, JPEG or PNG file.',
            'logo.max' => 'The logo may not be larger than 2MB.',
            'phone_number.regex' => 'Enter a phone number using digits and the usual separators.',
            'website.url' => 'Enter the full website address, including https://.',
            'default_language.in' => 'Choose either English or Urdu as the default language.',
            'timezone.in' => 'Choose a timezone from the list.',
            'date_format.in' => 'Choose a date format from the list.',
        ];
    }

    /**
     * Get the custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'institution_name' => 'institution name',
            'institution_name_urdu' => 'institution name (Urdu)',
            'address_urdu' => 'address (Urdu)',
            'phone_number' => 'phone number',
            'principal_name' => 'principal / imam name',
            'principal_name_urdu' => 'principal / imam name (Urdu)',
            'tagline_urdu' => 'tagline (Urdu)',
            'default_language' => 'default language',
            'date_format' => 'date format',
        ];
    }
}
