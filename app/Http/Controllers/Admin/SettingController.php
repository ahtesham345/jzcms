<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

/**
 * The institution's global settings.
 *
 * Two actions, and deliberately only two. There is no index, because one
 * record is not a list; no create, because the first save is the same form
 * as every save after it; no destroy, because an institution does not stop
 * having a name.
 *
 * Neither route carries an id. The row this writes is whichever one
 * Setting::current() resolves, so there is no parameter a browser could
 * change to reach a different record - and, with no create path and the
 * model refusing a second row, no second record for it to reach.
 *
 * Authorisation is the project's existing gate: the same
 * $this->authorize('permission.name') UserController uses, against
 * permissions the seeder grants to Super Admin and Admin.
 */
class SettingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Where uploaded logos live on the public disk.
     */
    private const LOGO_DIRECTORY = 'settings';

    /**
     * Show the settings form.
     *
     * The same view whether anything has been saved or not. current()
     * hands back the stored row, or an unsaved instance carrying the
     * defaults, so the first visit is a filled-in form rather than a
     * special case the view has to know about.
     */
    public function edit()
    {
        $this->authorize('settings.view');

        $settings = Setting::current();

        return view('settings.edit', [
            'settings' => $settings,
            // True once the institution has saved at least once. Used only
            // to word the page, never to decide what the form does.
            'isConfigured' => $settings->exists,
            'languages' => Setting::languageOptions(),
            'timezones' => Setting::timezoneOptions(),
            'dateFormats' => Setting::dateFormatOptions(),
        ]);
    }

    /**
     * Create or update the global settings.
     *
     * One action for both. The row is resolved rather than named, filled
     * from the validated data and saved; whether that is an INSERT or an
     * UPDATE is a detail of whether one existed, and not something the
     * request gets a say in.
     */
    public function update(UpdateSettingRequest $request)
    {
        $this->authorize('settings.update');

        $settings = Setting::current();

        $settings->fill($request->validated());

        // Handled apart from the validated data on purpose: logo is not
        // fillable, and what goes in the column is a path this method
        // produced, never a value the request supplied.
        //
        // When no file was uploaded the column is left exactly as it was -
        // neither this block nor fill() above touches it - which is the
        // whole of "keep the existing logo".
        $superseded = null;

        if ($request->hasFile('logo')) {
            $superseded = $settings->logo;

            $settings->logo = $request->file('logo')->store(self::LOGO_DIRECTORY, 'public');
        }

        $settings->save();

        // Only once the new path is committed. Deleting first would, on a
        // failed save, leave the row pointing at a file that is no longer
        // there.
        $this->deleteLogo($superseded);

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Settings saved successfully.');
    }

    /**
     * Remove a superseded logo file.
     *
     * Guarded on both the path and the file: a row whose logo was removed
     * from the disk by hand must not make a replacement fail.
     */
    private function deleteLogo(?string $path): void
    {
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
