<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::with(['timeEntries', 'subtasks']);

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('today')) {
            $query->whereDate('due_date', today());
        }

        $tasks = $query->orderBy('created_at', 'desc')->get();

        return response()->json($this->formatTasks($tasks));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'required|integer',
            'is_completed' => 'required|boolean',
            'parent_id' => 'nullable|string',
            'project_id' => 'required|string',
        ]);

        $task = Task::updateOrCreate(
            ['id' => $validated['id']],
            $validated
        );

        // Sync time entries
        if ($request->has('time_entries')) {
            $this->syncTimeEntries($task, $request->time_entries);
        }

        return response()->json($this->formatTask($task->load('timeEntries')), 201);
    }

    public function show($id)
    {
        $task = Task::with(['timeEntries', 'subtasks'])->findOrFail($id);
        return response()->json($this->formatTask($task));
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string',
            'description' => 'sometimes|string',
            'due_date' => 'sometimes|nullable|date',
            'priority' => 'sometimes|integer',
            'is_completed' => 'sometimes|boolean',
            'parent_id' => 'sometimes|nullable|string',
            'project_id' => 'sometimes|string',
        ]);

        $task->update($validated);

        if ($request->has('time_entries')) {
            $this->syncTimeEntries($task, $request->time_entries);
        }

        return response()->json($this->formatTask($task->load('timeEntries')));
    }

    public function destroy($id)
    {
        Task::destroy($id);
        return response()->json(['message' => 'Task deleted'], 200);
    }

    public function sync(Request $request)
    {
        $tasks = $request->json()->all();

        foreach ($tasks as $taskData) {
            $timeEntries = $taskData['time_entries'] ?? [];
            unset($taskData['time_entries']);

            $task = Task::updateOrCreate(
                ['id' => $taskData['id']],
                $taskData
            );

            $this->syncTimeEntries($task, $timeEntries);
        }

        return response()->json(['message' => 'Tasks synced'], 200);
    }

    private function syncTimeEntries(Task $task, array $entries)
    {
        // Delete existing entries
        $task->timeEntries()->delete();

        // Create new entries
        foreach ($entries as $entry) {
            TimeEntry::create([
                'id' => $entry['id'],
                'task_id' => $task->id,
                'start_time' => $entry['start_time'],
                'end_time' => $entry['end_time'] ?? null,
            ]);
        }
    }

    private function formatTask(Task $task)
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->toIso8601String(),
            'priority' => $task->priority,
            'is_completed' => $task->is_completed,
            'parent_id' => $task->parent_id,
            'subtask_ids' => $task->subtasks->pluck('id')->toArray(),
            'created_at' => $task->created_at->toIso8601String(),
            'project_id' => $task->project_id,
            'time_entries' => $task->timeEntries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'start_time' => $entry->start_time->toIso8601String(),
                    'end_time' => $entry->end_time?->toIso8601String(),
                ];
            })->toArray(),
            'is_synced' => $task->is_synced,
        ];
    }

    private function formatTasks($tasks)
    {
        return $tasks->map(fn($task) => $this->formatTask($task))->toArray();
    }
}
