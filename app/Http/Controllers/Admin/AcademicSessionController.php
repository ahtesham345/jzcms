<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicSessionRequest;
use App\Http\Requests\Admin\UpdateAcademicSessionRequest;
use App\Models\AcademicSession;
use Illuminate\Http\Request;

class AcademicSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AcademicSession::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $sessions = $query->latest()->paginate(10)->withQueryString();

        return view('academic-sessions.index', compact('sessions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('academic-sessions.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAcademicSessionRequest $request)
    {
        // If this session should be current, unset all other current sessions first
        if ($request->boolean('is_current')) {
            AcademicSession::query()->where('is_current', true)->update(['is_current' => false]);
        }

        $session = AcademicSession::create($request->validated());

        return redirect()
            ->route('academic-sessions.index')
            ->with('success', 'Academic session created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $session = AcademicSession::findOrFail($id);

        return view('academic-sessions.show', compact('session'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $session = AcademicSession::findOrFail($id);

        return view('academic-sessions.edit', compact('session'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAcademicSessionRequest $request, string $id)
    {
        $session = AcademicSession::findOrFail($id);

        // If this session should be current, unset all other current sessions first
        if ($request->boolean('is_current')) {
            AcademicSession::query()->where('is_current', true)->where('id', '!=', $id)->update(['is_current' => false]);
        }

        $session->update($request->validated());

        return redirect()
            ->route('academic-sessions.index')
            ->with('success', 'Academic session updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $session = AcademicSession::findOrFail($id);

            if ($session->is_current) {
                return redirect()
                    ->route('academic-sessions.index')
                    ->with('error', 'Cannot delete the current academic session.');
            }

            $session->delete();

            return redirect()
                ->route('academic-sessions.index')
                ->with('success', 'Academic session deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('academic-sessions.index')
                ->with('error', 'An error occurred while deleting the academic session.');
        }
    }
}
