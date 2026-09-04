<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicClassRequest;
use App\Http\Requests\Admin\UpdateAcademicClassRequest;
use App\Models\AcademicClass;
use App\Models\Department;
use Illuminate\Http\Request;

class AcademicClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AcademicClass::with('department');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $classes = $query->latest()->paginate(10)->withQueryString();
        $departments = Department::where('status', true)->orderBy('name')->get();

        return view('classes.index', compact('classes', 'departments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $departments = Department::where('status', true)->orderBy('name')->get();

        return view('classes.create', compact('departments'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAcademicClassRequest $request)
    {
        AcademicClass::create($request->validated());

        return redirect()
            ->route('classes.index')
            ->with('success', 'Class created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $class = AcademicClass::with('department')->findOrFail($id);

        return view('classes.show', compact('class'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $class = AcademicClass::findOrFail($id);
        $departments = Department::where('status', true)->orderBy('name')->get();

        return view('classes.edit', compact('class', 'departments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAcademicClassRequest $request, string $id)
    {
        $class = AcademicClass::findOrFail($id);

        $class->update($request->validated());

        return redirect()
            ->route('classes.index')
            ->with('success', 'Class updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $class = AcademicClass::findOrFail($id);

            $class->delete();

            return redirect()
                ->route('classes.index')
                ->with('success', 'Class deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('classes.index')
                ->with('error', 'An error occurred while deleting the class.');
        }
    }
}
