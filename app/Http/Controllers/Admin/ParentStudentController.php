<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LinkParentStudentRequest;
use App\Models\ParentGuardian;
use App\Models\Student;

/**
 * Manages the links between parents and students.
 *
 * The same link is reachable from both profiles, so each action exists in
 * two flavours that differ only in where they redirect back to.
 */
class ParentStudentController extends Controller
{
    /**
     * Link a student to a parent, from the parent profile.
     */
    public function storeFromParent(LinkParentStudentRequest $request, string $parent)
    {
        $this->link($request->validated());

        return redirect()
            ->route('parents.show', $parent)
            ->with('success', 'Student linked successfully.');
    }

    /**
     * Link a parent to a student, from the student profile.
     */
    public function storeFromStudent(LinkParentStudentRequest $request, string $student)
    {
        $this->link($request->validated());

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Parent linked successfully.');
    }

    /**
     * Unlink a student from a parent, from the parent profile.
     */
    public function destroyFromParent(string $parent, string $student)
    {
        $this->unlink($parent, $student);

        return redirect()
            ->route('parents.show', $parent)
            ->with('success', 'Student unlinked successfully.');
    }

    /**
     * Unlink a parent from a student, from the student profile.
     */
    public function destroyFromStudent(string $student, string $parent)
    {
        $this->unlink($parent, $student);

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Parent unlinked successfully.');
    }

    /**
     * Create the link.
     *
     * @param  array<string, mixed>  $data
     */
    private function link(array $data): void
    {
        ParentGuardian::findOrFail($data['parent_id'])->linkStudent(
            (int) $data['student_id'],
            $data['relationship_type'],
            (bool) ($data['is_primary'] ?? false),
        );
    }

    /**
     * Remove the link, and nothing else.
     *
     * Both records are loaded first so a bad id is a 404 rather than a
     * silent no-op. Only the pivot row is deleted.
     */
    private function unlink(string $parentId, string $studentId): void
    {
        $parent = ParentGuardian::findOrFail($parentId);
        $student = Student::findOrFail($studentId);

        $parent->unlinkStudent($student->id);
    }
}
