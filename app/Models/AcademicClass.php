<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicClass extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'academic_classes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'department_id',
        'name',
        'code',
        'description',
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
            'status' => 'boolean',
        ];
    }

    /**
     * Get the department that owns the class.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the teachers assigned to this class.
     */
    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'teacher_class')
            ->withTimestamps();
    }

    /**
     * Get the sections for the class.
     */
    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Get the students for this class.
     */
    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
