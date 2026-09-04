<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the session the institution is currently running.
     *
     * Current *and* active, both conditions, deliberately: is_current names
     * the session in progress and status says whether it is in use at all,
     * so a session that has been switched off is not the one anything should
     * be filed into however the flag was left.
     *
     * One definition, here, rather than the condition written out at each
     * call site. Admissions ask this question from three places - the public
     * form, the admin form and the passed students notice - and they must
     * never disagree about the answer.
     *
     * Null when the institution has no current session set up. Callers
     * decide what that means for them; nothing is invented here, and in
     * particular no session is guessed from today's date. A session is a
     * declared academic year, not a date range that happens to contain now.
     */
    public static function current(): ?self
    {
        return static::query()
            ->where('status', true)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Get the id of the current session, or null when there is none.
     */
    public static function currentId(): ?int
    {
        return static::current()?->id;
    }

    /**
     * Set this session as current and unset all others.
     */
    public function setAsCurrent(): void
    {
        static::query()->where('is_current', true)->update(['is_current' => false]);
        $this->update(['is_current' => true]);
    }

    /**
     * Get the students for this academic session.
     */
    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
