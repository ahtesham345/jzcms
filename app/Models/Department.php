<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'established_date',
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
            // A calendar date and nothing more: the day the department
            // opened, with no time and no session behind it.
            'established_date' => 'date',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the classes for the department.
     */
    public function academicClasses()
    {
        return $this->hasMany(AcademicClass::class);
    }

    /**
     * Get the students for this department.
     */
    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
