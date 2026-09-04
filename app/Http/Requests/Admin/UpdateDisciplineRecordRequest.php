<?php

namespace App\Http\Requests\Admin;

/**
 * Corrects one existing discipline record.
 *
 * The store rules unchanged. An incident may be re-filed against a
 * different student - a correction the office genuinely needs when an entry
 * was made under the wrong name - and the student_id rule already checks
 * that whoever it is moved to exists.
 *
 * recorded_by is untouched by an update, as it is by a create: it names who
 * wrote the record down, and correcting the text of an incident does not
 * change who first entered it.
 */
class UpdateDisciplineRecordRequest extends StoreDisciplineRecordRequest
{
    //
}
