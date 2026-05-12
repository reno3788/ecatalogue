<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Workflow;
use App\Models\OrderApprovalLog;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * Resolves the most suitable workflow rule for the given Order context.
     */
    public function getApplicableWorkflow(Order $order): ?Workflow
    {
        // 1. Priority: Targeted Company match with sufficient volume
        $workflow = Workflow::with(['steps.approvers'])
            ->where('is_active', true)
            ->where('company_id', $order->company_id)
            ->where('min_amount', '>=', $order->total)
            ->orderBy('min_amount', 'asc')
            ->first();

        if ($workflow) {
            return $workflow;
        }

        // 2. Fallback: General generic rules without explicit company locks
        return Workflow::with(['steps.approvers'])
            ->where('is_active', true)
            ->whereNull('company_id')
            ->where('min_amount', '>=', $order->total)
            ->orderBy('min_amount', 'asc')
            ->first();
    }

    /**
     * Determines if the requested action represents the target workflow advancement window.
     * In this system, the workflow gates the specific transition between 'Submitted' and 'Approved'.
     */
    public function isWorkflowTransition(string $currentStatus, string $targetStatus): bool
    {
        return ($currentStatus === 'Submitted' && $targetStatus === 'Approved');
    }

    /**
     * Evaluates authorization matrix confirming if identity commands valid step coverage.
     */
    public function canUserApprove(Order $order, User $user): bool
    {
        // 1. Priority: Identify dynamic rule matrix configuration
        $workflow = $this->getApplicableWorkflow($order);
        
        // Fail-fast bypass: Only Root Administrator bypasses lack of logic constraints.
        if (!$workflow) {
            return $user->hasRole('admin'); 
        }

        // 2. Deep Scan Completion State: Evaluate active remaining stages leveraging resilience heuristic
        $pendingSteps = $this->getPendingSteps($order, $workflow);
        
        // 3. Autonomous Resolution Gateway (Self-Heal):
        // If all lifecycle stages have achieved verifiable consensus, IMMEDIATELY finalize state.
        // This MUST occur before identity validation to ensure stuck processes resolve globally,
        // regardless of whether the active viewing user is an existing approver!
        if ($pendingSteps->isEmpty()) {
            if ($order->status === 'Submitted') {
                $order->update(['status' => 'Approved']);
            }
            return false; // Logic cycle effectively terminated
        }

        // 4. Rigid Accountability Lockout: Single Identity may not duplicate approval signatures.
        // (Separation of duties enforcement).
        $hasAlreadySigned = OrderApprovalLog::where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->where('action', 'LIKE', 'Approved%')
            ->exists();
            
        if ($hasAlreadySigned) {
            return false; // User locked out of subsequent tiers for this specific order
        }

        // 5. Sequence Pattern Validation
        if ($workflow->require_sequential) {
            $nextStep = $pendingSteps->first();
            return $nextStep->approvers->contains('id', $user->id);
        } else {
            return $pendingSteps->contains(function($step) use ($user) {
                return $step->approvers->contains('id', $user->id);
            });
        }
    }

    /**
     * Captures authorized actions, updates audit trail, and triggers final object promotion if completion reached.
     * @return string Next implied Order State: 'Submitted' (still pending steps) OR 'Approved' (workflow completed).
     */
    public function executeWorkflowAction(Order $order, User $user, string $note = null): string
    {
        $workflow = $this->getApplicableWorkflow($order);
        
        // Immediate fallback if no controls active
        if (!$workflow) {
            return 'Approved';
        }

        $pendingSteps = $this->getPendingSteps($order, $workflow);
        
        // Identify exact matching step to apply assignment stamp to log
        $targetStep = null;
        if ($workflow->require_sequential) {
            $candidate = $pendingSteps->first();
            if ($candidate && $candidate->approvers->contains('id', $user->id)) {
                $targetStep = $candidate;
            }
        } else {
            $targetStep = $pendingSteps->first(function($step) use ($user) {
                return $step->approvers->contains('id', $user->id);
            });
        }

        if (!$targetStep) {
            throw new \Exception("Current user possesses no active assignment credentials supporting this sequence node.");
        }

        // Persist historic trace including the vital step identifier link
        OrderApprovalLog::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'workflow_step_id' => $targetStep->id,
            'action' => 'Approved (' . $targetStep->name . ')',
            'note' => $note ?: 'Step verification signed by ' . $user->name
        ]);

        // Check remaining steps POST-injection to determine logical outcome
        // Refresh payload
        $remainingCount = $this->getPendingSteps($order, $workflow)->count();

        // If ZERO remaining, entire chain resolved! Elevate to target status!
        return $remainingCount === 0 ? 'Approved' : 'Submitted';
    }

    /**
     * Retrieves filtered collection of remaining target steps yet to receive signed verification.
     * Implements high-resilience heuristic engine that heals broken configurations by 
     * performing cascading assignment attribution when historical step IDs drift or are deleted.
     */
    protected function getPendingSteps(Order $order, Workflow $workflow)
    {
        // 1. Collect audit logs for approval actions ordered chronologically
        $logs = OrderApprovalLog::where('order_id', $order->id)
            ->where('action', 'LIKE', 'Approved%') 
            ->orderBy('id', 'asc')
            ->get();

        // 2. Cast active workflow skeleton into standard consumable array based on rank
        $stepsArray = $workflow->steps->sortBy('sort_order')->values()->all();

        foreach ($logs as $log) {
            $matchedIndex = -1;
            
            // A. Priority: Strict Identifier matching (handles unmodified workflows perfectly)
            if ($log->workflow_step_id) {
                foreach ($stepsArray as $idx => $step) {
                    if ($step->id == $log->workflow_step_id) {
                        $matchedIndex = $idx;
                        break;
                    }
                }
            }
            
            // B. Fallback: Identity-Drift Resilience heuristic. 
            // If step records were erased/reinstantiated, map Orphaned Log to the EARLIEST vacant slot.
            if ($matchedIndex === -1 && count($stepsArray) > 0) {
                $matchedIndex = 0; 
            }
            
            // C. Consumptive attribution removes filled step slot from active rotation
            if ($matchedIndex !== -1) {
                array_splice($stepsArray, $matchedIndex, 1);
            }
        }

        // 3. Reconsolidate remaining vacancy artifacts back into framework collection
        return collect($stepsArray);
    }
}
