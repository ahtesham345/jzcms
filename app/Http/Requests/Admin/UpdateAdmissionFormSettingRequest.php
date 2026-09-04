<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Saves when the public admission form is reachable.
 *
 * The form asks for a date and a time at each end because that is what is
 * comfortable to fill in; the settings row stores one instant at each end.
 * Recombining the pairs happens here, in scheduleData(), so the controller
 * writes columns rather than assembling datetimes and no second copy of the
 * combining rule exists anywhere else.
 *
 * The four schedule fields are required only when the form is being enabled.
 * That is the difference between the two ways of closing admissions: the
 * toggle can be switched off on its own, leaving the saved window in place
 * for next time, while enabling one demands a complete window - an enabled
 * schedule missing an end is the one combination that could leave the public
 * form unbounded.
 */
class UpdateAdmissionFormSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The controller asks the project's existing permission gate before this
     * class is reached, and the route is behind auth. This class is about
     * what the request may say.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the toggle and blank the untouched schedule inputs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // An unticked checkbox is not submitted at all, so the absence
            // of the field is what "off" looks like.
            'admission_form_enabled' => $this->boolean('admission_form_enabled'),
        ]);

        // An emptied date input arrives as an empty string. Stored as '' it
        // would fail to cast to a datetime; treated as null it is simply an
        // end of the window that has not been set.
        foreach (['opens_date', 'opens_time', 'closes_date', 'closes_time'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * Determine whether this request is switching the admission form on.
     */
    private function isEnabling(): bool
    {
        return $this->boolean('admission_form_enabled');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Only an enabled window has to be complete. A disabled one may be
        // saved half filled in, or not filled in at all.
        $whenEnabled = $this->isEnabling() ? 'required' : 'nullable';

        return [
            'admission_form_enabled' => ['boolean'],

            'opens_date' => [$whenEnabled, 'nullable', 'date_format:Y-m-d'],
            'closes_date' => [$whenEnabled, 'nullable', 'date_format:Y-m-d'],

            // Browsers post H:i, and some post H:i:s. Both are accepted, the
            // same pair the admission test time already allows.
            'opens_time' => [$whenEnabled, 'nullable', 'date_format:H:i,H:i:s'],
            'closes_time' => [$whenEnabled, 'nullable', 'date_format:H:i,H:i:s'],
        ];
    }

    /**
     * Reject a window that closes before it opens.
     *
     * Compared as two instants rather than as a date and a time apiece, so a
     * window spanning several days is judged on when it actually ends: an
     * 08:00 opening on the 10th and a 23:59 closing on the 20th is valid,
     * while a 10:00 opening and a 09:00 closing on the same day is not.
     *
     * Equal ends are rejected too. A window that opens and closes at the same
     * instant is open for no time at all, which is a mistake rather than a
     * configuration.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $opens = $this->combine('opens_date', 'opens_time');
                $closes = $this->combine('closes_date', 'closes_time');

                if ($opens === null || $closes === null) {
                    return;
                }

                if ($closes->lessThanOrEqualTo($opens)) {
                    $validator->errors()->add(
                        'closes_date',
                        'The admission closing date and time must be after the opening date and time.'
                    );
                }
            },
        ];
    }

    /**
     * Get the settings columns this request writes.
     *
     * The controller fills the settings row from this rather than from
     * validated(), because what is stored is not the shape that was posted:
     * four inputs in, two instants and a flag out.
     *
     * @return array<string, mixed>
     */
    public function scheduleData(): array
    {
        return [
            'admission_form_enabled' => $this->isEnabling(),
            // Kept even while disabled, so switching the form back on does
            // not mean typing the window in again.
            'admission_form_opens_at' => $this->combine('opens_date', 'opens_time'),
            'admission_form_closes_at' => $this->combine('closes_date', 'closes_time'),
        ];
    }

    /**
     * Build one end of the window from its date and time inputs.
     *
     * Null unless both halves are present: half a moment is not a moment,
     * and the open check treats a missing end as closed rather than
     * unbounded.
     *
     * Parsed in the application's own timezone, which is where now() is
     * measured, so a window saved as 08:00 means 08:00 to the server that
     * later compares against it.
     */
    private function combine(string $dateField, string $timeField): ?Carbon
    {
        $date = $this->input($dateField);
        $time = $this->input($timeField);

        if (blank($date) || blank($time)) {
            return null;
        }

        // Seconds are dropped rather than trusted: a browser that posts
        // H:i:s must not make a window end at a different second from one
        // that posts H:i.
        $time = substr((string) $time, 0, 5);

        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", config('app.timezone'));
    }

    /**
     * Get the custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'admission_form_enabled' => 'admission form',
            'opens_date' => 'opening date',
            'opens_time' => 'opening time',
            'closes_date' => 'closing date',
            'closes_time' => 'closing time',
        ];
    }
}
