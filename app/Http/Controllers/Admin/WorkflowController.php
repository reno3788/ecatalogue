<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkflowController extends Controller
{
    public function index()
    {
        $workflows = Workflow::with(['company', 'steps.approvers'])->latest()->get();
        $companies = Company::all();
        
        // Only get users eligible to be approvers
        $approverUsers = User::role('supplier_approver')->get();

        return Inertia::render('Admin/ApprovalWorkflow/Index', [
            'workflows' => $workflows,
            'companies' => $companies,
            'approverUsers' => $approverUsers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'min_amount' => 'required|numeric|min:0',
            'require_sequential' => 'required|boolean',
            'steps' => 'required|array|min:1',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.description' => 'nullable|string',
            'steps.*.approver_ids' => 'required|array|min:1',
            'steps.*.approver_ids.*' => 'exists:users,id',
        ]);

        DB::transaction(function () use ($validated) {
            $workflow = Workflow::create([
                'name' => $validated['name'],
                'company_id' => $validated['company_id'],
                'min_amount' => $validated['min_amount'],
                'require_sequential' => $validated['require_sequential'],
                'is_active' => true,
            ]);

            foreach ($validated['steps'] as $index => $stepData) {
                $step = WorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'sort_order' => $index + 1,
                    'name' => $stepData['name'],
                    'description' => $stepData['description'] ?? null,
                ]);

                $step->approvers()->sync($stepData['approver_ids']);
            }
        });

        return redirect()->back()->with('success', 'Workflow rule created successfully.');
    }

    public function update(Request $request, Workflow $workflow)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'min_amount' => 'required|numeric|min:0',
            'require_sequential' => 'required|boolean',
            'steps' => 'required|array|min:1',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.description' => 'nullable|string',
            'steps.*.approver_ids' => 'required|array|min:1',
            'steps.*.approver_ids.*' => 'exists:users,id',
        ]);

        DB::transaction(function () use ($validated, $workflow) {
            $workflow->update([
                'name' => $validated['name'],
                'company_id' => $validated['company_id'],
                'min_amount' => $validated['min_amount'],
                'require_sequential' => $validated['require_sequential'],
            ]);

            // Re-sync steps by clearing and rebuilding to maintain perfect ordering and logic integrity
            $workflow->steps()->delete();

            foreach ($validated['steps'] as $index => $stepData) {
                $step = WorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'sort_order' => $index + 1,
                    'name' => $stepData['name'],
                    'description' => $stepData['description'] ?? null,
                ]);

                $step->approvers()->sync($stepData['approver_ids']);
            }
        });

        return redirect()->back()->with('success', 'Workflow rule updated successfully.');
    }

    public function destroy(Workflow $workflow)
    {
        // Cascade takes care of steps and pivots via DB constraints
        $workflow->delete();
        
        return redirect()->back()->with('success', 'Workflow rule deleted successfully.');
    }
}
