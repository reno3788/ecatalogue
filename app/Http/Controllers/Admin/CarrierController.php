<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use Illuminate\Http\Request;

class CarrierController extends Controller
{
    /**
     * Store a newly created shipping carrier in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:carriers,name',
            'tracking_url_pattern' => 'nullable|string|max:500',
        ]);

        Carrier::create($validated);

        return redirect()->back()->with('success', 'Carrier created successfully.');
    }

    /**
     * Update the specified shipping carrier in storage.
     */
    public function update(Request $request, Carrier $carrier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:carriers,name,' . $carrier->id,
            'tracking_url_pattern' => 'nullable|string|max:500',
        ]);

        $carrier->update($validated);

        return redirect()->back()->with('success', 'Carrier updated successfully.');
    }

    /**
     * Remove the specified shipping carrier from storage.
     */
    public function destroy(Carrier $carrier)
    {
        $carrier->delete();

        return redirect()->back()->with('success', 'Carrier deleted successfully.');
    }
}
