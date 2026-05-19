<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index()
    {
        // Both admins and suppliers can view the companies index.
        // The Company model's getStatusAttribute accessor normalizes legacy boolean status → 'Active'/'Inactive'.
        $companies = Company::latest()->get();

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
        ]);
    }


    public function store(Request $request)
    {
        // Restrict write operations to admin only
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abeta_id' => 'nullable|string|max:255',
            'status' => 'required|string|in:Active,Inactive',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'taxid' => 'nullable|string|max:255',
            'taxaddress' => 'nullable|string',
            'punchout_enabled' => 'boolean',
            'punchout_gateway' => 'nullable|string|max:255',
            'punchout_url' => 'nullable|string|max:1000',
            'punchout_identity' => 'nullable|string|max:255',
            'punchout_shared_secret' => 'nullable|string|max:255',
            'bargaining_enabled' => 'boolean',
        ]);


        Company::create($validated);

        return redirect()->back()->with('success', 'Company created successfully.');
    }

    public function update(Request $request, Company $company)
    {
        // Restrict write operations to admin only
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abeta_id' => 'nullable|string|max:255',
            'status' => 'required|string|in:Active,Inactive',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'taxid' => 'nullable|string|max:255',
            'taxaddress' => 'nullable|string',
            'punchout_enabled' => 'boolean',
            'punchout_gateway' => 'nullable|string|max:255',
            'punchout_url' => 'nullable|string|max:1000',
            'punchout_identity' => 'nullable|string|max:255',
            'punchout_shared_secret' => 'nullable|string|max:255',
            'bargaining_enabled' => 'boolean',
        ]);

        $company->update($validated);

        return redirect()->back()->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company)
    {
        // Restrict write operations to admin only
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized action.');
        }

        // Add safety check: check if users belong to this company
        if ($company->users()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete company. It still has active users.');
        }

        $company->delete();

        return redirect()->back()->with('success', 'Company deleted successfully.');
    }
}
