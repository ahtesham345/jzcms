<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreParentRequest;
use App\Http\Requests\Admin\UpdateParentRequest;
use App\Models\ParentGuardian;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ParentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ParentGuardian::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('parent_id', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    ->orWhere('alternate_mobile', 'like', "%{$search}%");
            });
        }

        // Filter by Status
        if ($request->filled('parent_status')) {
            $query->where('parent_status', $request->parent_status);
        }

        $parents = $query->latest()->paginate(10)->withQueryString();

        return view('parents.index', compact('parents'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('parents.create', [
            // Preview only. The authoritative ID is allocated again by
            // createWithParentId() inside the save transaction.
            'nextParentId' => ParentGuardian::nextParentId(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreParentRequest $request)
    {
        $data = $request->validated();
        unset($data['parent_id']);

        // Only the path is stored; the file lives on the public disk.
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('parents', 'public');
        } else {
            unset($data['photo']);
        }

        $parent = ParentGuardian::createWithParentId($data);

        return redirect()
            ->route('parents.index')
            ->with('success', "Parent created successfully with ID {$parent->parent_id}.");
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $parent = ParentGuardian::with([
            'students.department',
            'students.academicClass',
            'students.section',
        ])->findOrFail($id);

        return view('parents.show', [
            'parent' => $parent,
            // Only students that are not linked to this parent already.
            'linkableStudents' => Student::whereNotIn('id', $parent->students->pluck('id'))
                ->orderBy('full_name')
                ->get(),
            'relationshipTypes' => ParentGuardian::RELATIONSHIP_TYPES,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $parent = ParentGuardian::findOrFail($id);

        return view('parents.edit', compact('parent'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateParentRequest $request, string $id)
    {
        $parent = ParentGuardian::findOrFail($id);
        $data = $request->validated();

        // parent_id is never updatable.
        unset($data['parent_id']);

        if ($request->hasFile('photo')) {
            // Replace: remove the previous file so it is not orphaned.
            $this->deletePhoto($parent);

            $data['photo'] = $request->file('photo')->store('parents', 'public');
        } else {
            // No new upload: keep the existing photo rather than nulling it.
            unset($data['photo']);
        }

        $parent->update($data);

        return redirect()
            ->route('parents.index')
            ->with('success', 'Parent updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $parent = ParentGuardian::findOrFail($id);

        $this->deletePhoto($parent);
        $parent->delete();

        return redirect()
            ->route('parents.index')
            ->with('success', 'Parent deleted successfully.');
    }

    /**
     * Delete the parent's stored photo if one exists.
     */
    private function deletePhoto(ParentGuardian $parent): void
    {
        if ($parent->photo && Storage::disk('public')->exists($parent->photo)) {
            Storage::disk('public')->delete($parent->photo);
        }
    }
}
