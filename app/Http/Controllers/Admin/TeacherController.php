<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\AcademicClass;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Teacher::with('academicClasses');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('teacher_id', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    ->orWhere('alternate_mobile', 'like', "%{$search}%");
            });
        }

        // Filter by Status
        if ($request->filled('teacher_status')) {
            $query->where('teacher_status', $request->teacher_status);
        }

        $teachers = $query->latest()->paginate(10)->withQueryString();

        return view('teachers.index', compact('teachers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('teachers.create', [
            'academicClasses' => $this->activeClasses(),
            // Preview only. The authoritative ID is allocated again by
            // createWithTeacherId() inside the save transaction.
            'nextTeacherId' => Teacher::nextTeacherId(),
        ]);
    }

    /**
     * Get the active classes, with their department, for the form select.
     */
    private function activeClasses()
    {
        return AcademicClass::with('department')
            ->where('status', true)
            ->orderBy('department_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the selected class ids, ready for syncing the pivot.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function selectedClassIds(array $data): array
    {
        // unique() guards against a request repeating the same id, which the
        // pivot's unique constraint would otherwise reject.
        return collect($data['academic_class_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeacherRequest $request)
    {
        $data = $request->validated();
        $classIds = $this->selectedClassIds($data);
        unset($data['academic_class_ids'], $data['teacher_id']);

        // Only the path is stored; the file lives on the public disk.
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('teachers', 'public');
        } else {
            unset($data['photo']);
        }

        $teacher = Teacher::createWithTeacherId($data);
        $teacher->academicClasses()->sync($classIds);

        return redirect()
            ->route('teachers.index')
            ->with('success', "Teacher created successfully with ID {$teacher->teacher_id}.");
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $teacher = Teacher::with('academicClasses.department')->findOrFail($id);

        return view('teachers.show', compact('teacher'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $teacher = Teacher::with('academicClasses')->findOrFail($id);

        return view('teachers.edit', [
            'teacher' => $teacher,
            'academicClasses' => $this->activeClasses(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeacherRequest $request, string $id)
    {
        $teacher = Teacher::findOrFail($id);
        $data = $request->validated();

        $classIds = $this->selectedClassIds($data);
        // teacher_id is never updatable, and the pivot is synced separately.
        unset($data['academic_class_ids'], $data['teacher_id']);

        if ($request->hasFile('photo')) {
            // Replace: remove the previous file so it is not orphaned.
            $this->deletePhoto($teacher);

            $data['photo'] = $request->file('photo')->store('teachers', 'public');
        } else {
            // No new upload: keep the existing photo rather than nulling it.
            unset($data['photo']);
        }

        $teacher->update($data);

        // sync() adds new assignments and removes deselected ones.
        $teacher->academicClasses()->sync($classIds);

        return redirect()
            ->route('teachers.index')
            ->with('success', 'Teacher updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $teacher = Teacher::findOrFail($id);

        $this->deletePhoto($teacher);
        $teacher->delete();

        return redirect()
            ->route('teachers.index')
            ->with('success', 'Teacher deleted successfully.');
    }

    /**
     * Delete the teacher's stored photo if one exists.
     */
    private function deletePhoto(Teacher $teacher): void
    {
        if ($teacher->photo && Storage::disk('public')->exists($teacher->photo)) {
            Storage::disk('public')->delete($teacher->photo);
        }
    }
}
