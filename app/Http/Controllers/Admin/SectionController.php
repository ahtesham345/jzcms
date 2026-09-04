<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Models\AcademicClass;
use App\Models\Department;
use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Section::with('academicClass.department');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
        }

        if ($request->filled('department')) {
            $query->whereHas('academicClass', function ($q) use ($request) {
                $q->where('department_id', $request->department);
            });
        }

        if ($request->filled('class')) {
            $query->where('academic_class_id', $request->class);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sections = $query->latest()->paginate(10)->withQueryString();
        $departments = Department::where('status', true)->orderBy('name')->get();
        $classes = AcademicClass::where('status', true)->with('department')->orderBy('name')->get();

        return view('sections.index', compact('sections', 'departments', 'classes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $classes = AcademicClass::where('status', true)->with('department')->orderBy('name')->get();

        return view('sections.create', compact('classes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSectionRequest $request)
    {
        Section::create($request->validated());

        return redirect()
            ->route('sections.index')
            ->with('success', 'Section created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $section = Section::with('academicClass.department')->findOrFail($id);

        return view('sections.show', compact('section'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $section = Section::findOrFail($id);
        $classes = AcademicClass::where('status', true)->with('department')->orderBy('name')->get();

        return view('sections.edit', compact('section', 'classes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSectionRequest $request, string $id)
    {
        $section = Section::findOrFail($id);

        $section->update($request->validated());

        return redirect()
            ->route('sections.index')
            ->with('success', 'Section updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $section = Section::findOrFail($id);

            $section->delete();

            return redirect()
                ->route('sections.index')
                ->with('success', 'Section deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('sections.index')
                ->with('error', 'An error occurred while deleting the section.');
        }
    }
}
