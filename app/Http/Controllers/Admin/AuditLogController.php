<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class AuditLogController extends Controller
{
    /**
     * Display a listing of system-wide audit trail logs.
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->latest();

        // Event Type Filtering
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        // User Filtering
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // General Text Search Across Morph Targets & Attributes
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('auditable_type', 'like', "%{$searchTerm}%")
                  ->orWhere('ip_address', 'like', "%{$searchTerm}%")
                  ->orWhere('user_agent', 'like', "%{$searchTerm}%")
                  ->orWhereHas('user', function($userQ) use ($searchTerm) {
                      $userQ->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                  });
            });
        }

        $logs = $query->paginate(20)->through(function($log) {
            // Derive clean model name from App\Models\AppSetting -> 'App Setting'
            $rawModel = class_basename($log->auditable_type);
            $formattedModel = trim(implode(' ', preg_split('/(?=[A-Z])/', $rawModel)));

            // Try to construct a friendly target name fallback
            $targetName = '#' . $log->auditable_id;
            
            // Dynamically check if standard attributes are present in serialized states to name it
            $state = $log->new_values ?: $log->old_values;
            if (!empty($state)) {
                if (isset($state['name'])) {
                    $targetName = $state['name'];
                } elseif (isset($state['sku'])) {
                    $targetName = $state['sku'];
                } elseif (isset($state['email'])) {
                    $targetName = $state['email'];
                }
            }

            return [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'event' => $log->event,
                'auditable_type' => $formattedModel,
                'auditable_id' => $log->auditable_id,
                'target_name' => $targetName,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'url' => $log->url,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'time_ago' => $log->created_at->diffForHumans(),
            ];
        });

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['event', 'user_id', 'search']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
}
