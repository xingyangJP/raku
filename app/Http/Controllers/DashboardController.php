<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Estimate;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // Show tasks purely based on approval_flow (未承認が存在するもの)。status には依存しない。
        $estimatesWithFlow = Estimate::whereNotNull('approval_flow')->get();
        $toDoEstimates = [];

        foreach ($estimatesWithFlow as $estimate) {
            $approvalFlow = is_array($estimate->approval_flow)
                ? $estimate->approval_flow
                : json_decode($estimate->approval_flow, true);
            if (!is_array($approvalFlow) || empty($approvalFlow)) {
                continue; // Skip if approval_flow is not a valid array or empty
            }

            $isCurrentUserNextApprover = false;
            $waitingForApproverName = null;
            $isCurrentUserInFlow = false;

            // Check if current user is in the flow at all
            foreach ($approvalFlow as $approver) {
                $approverIdInFlow = $approver['id'] ?? null;
                $matchesLocalId = is_numeric($approverIdInFlow) && (int)$approverIdInFlow === (int)$user->id;
                $approverIdInFlowStr = is_null($approverIdInFlow) ? '' : (string)$approverIdInFlow;
                $userExt = (string)($user->external_user_id ?? '');
                $matchesExternalId = ($approverIdInFlowStr !== '') && ($userExt !== '') && ($approverIdInFlowStr === $userExt);

                if ($matchesLocalId || $matchesExternalId) {
                    $isCurrentUserInFlow = true;
                    break;
                }
            }

            $currentStepIndex = -1;
            foreach ($approvalFlow as $idx => $approver) {
                if (empty($approver['approved_at'])) {
                    $currentStepIndex = $idx;
                    break;
                }
            }

            if ($currentStepIndex !== -1) {
                $currentApprover = $approvalFlow[$currentStepIndex];

                $approverIdInFlow = $currentApprover['id'] ?? null;
                $matchesLocalId = is_numeric($approverIdInFlow) && (int)$approverIdInFlow === (int)$user->id;
                $approverIdInFlowStr = is_null($approverIdInFlow) ? '' : (string)$approverIdInFlow;
                $userExt = (string)($user->external_user_id ?? '');
                $matchesExternalId = ($approverIdInFlowStr !== '') && ($userExt !== '') && ($approverIdInFlowStr === $userExt);
                if ($matchesLocalId || $matchesExternalId) {
                    $isCurrentUserNextApprover = true;
                }

                if (!$isCurrentUserNextApprover) {
                    $waitingForApproverName = $currentApprover['name'];
                }
            } else {
                // All steps approved → ダッシュボード対象外
                continue;
            }

            $status_for_dashboard = '';
            if ($isCurrentUserNextApprover) {
                $status_for_dashboard = '確認して承認';
            } elseif ($waitingForApproverName) {
                $status_for_dashboard = "{$waitingForApproverName}さんの承認待ち";
            } else {
                // Should not happen if there is a current step, but as a fallback
                continue;
            }

            $toDoEstimates[] = [
                'id' => $estimate->id,
                'title' => $estimate->title,
                'issue_date' => $estimate->issue_date,
                'status_for_dashboard' => $status_for_dashboard,
                'estimate_number' => $estimate->estimate_number,
                'estimate' => $estimate->toArray(),
                'is_current_user_in_flow' => $isCurrentUserInFlow,
            ];
        }

        usort($toDoEstimates, function($a, $b) {
            return strtotime($b['issue_date']) - strtotime($a['issue_date']);
        });

        return Inertia::render('Dashboard', [
            'toDoEstimates' => $toDoEstimates,
        ]);
    }
}