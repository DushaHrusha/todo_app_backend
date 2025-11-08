<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::orderBy('order')->get();
        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'name' => 'required|string',
            'color' => 'required|string',
            'order' => 'required|integer',
        ]);

        $project = Project::updateOrCreate(
            ['id' => $validated['id']],
            $validated
        );

        return response()->json($project, 201);
    }

    public function show($id)
    {
        $project = Project::with('tasks')->findOrFail($id);
        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'color' => 'sometimes|string',
            'order' => 'sometimes|integer',
        ]);

        $project->update($validated);
        return response()->json($project);
    }

    public function destroy($id)
    {
        if ($id === 'inbox') {
            return response()->json(['error' => 'Cannot delete inbox'], 403);
        }

        Project::destroy($id);
        return response()->json(['message' => 'Project deleted'], 200);
    }

    public function sync(Request $request)
    {
        $projects = $request->json()->all();

        foreach ($projects as $projectData) {
            Project::updateOrCreate(
                ['id' => $projectData['id']],
                $projectData
            );
        }

        return response()->json(['message' => 'Projects synced'], 200);
    }
}
