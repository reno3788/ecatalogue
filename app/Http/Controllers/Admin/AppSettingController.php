<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AppSettingController extends Controller
{
    /**
     * Show the application settings page.
     */
    public function index()
    {
        $settings = AppSetting::first() ?? new AppSetting();
        $carriers = \App\Models\Carrier::all();

        return Inertia::render('Admin/AppSettings/Index', [
            'settings' => $settings,
            'carriers' => $carriers,
        ]);
    }

    /**
     * Update the application settings.
     */
    public function update(Request $request)
    {
        $settings = AppSetting::first();
        if (!$settings) {
            $settings = new AppSetting();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'currency' => 'required|string|max:10',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string',
            'smtp_encryption' => 'nullable|string|max:20',
            'smtp_from_address' => 'nullable|email|max:255',
            'smtp_from_name' => 'nullable|string|max:255',
        ]);

        $data = [
            'name' => $validated['name'],
            'currency' => $validated['currency'],
            'smtp_host' => $validated['smtp_host'] ?? null,
            'smtp_port' => $validated['smtp_port'] ?? null,
            'smtp_username' => $validated['smtp_username'] ?? null,
            'smtp_encryption' => $validated['smtp_encryption'] ?? null,
            'smtp_from_address' => $validated['smtp_from_address'] ?? null,
            'smtp_from_name' => $validated['smtp_from_name'] ?? null,
        ];

        // Intentionally only update password if a non-empty string was supplied
        if ($request->filled('smtp_password')) {
            $data['smtp_password'] = $validated['smtp_password'];
        }

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $settings->fill($data);
        $settings->save();

        return redirect()->back()->with('success', 'App configuration updated successfully.');
    }

    /**
     * Dynamically test the incoming SMTP payload.
     */
    public function testSmtp(Request $request)
    {
        $request->validate([
            'smtp_host' => 'required|string',
            'smtp_port' => 'required',
            'smtp_from_address' => 'required|email',
            'test_email' => 'required|email',
        ]);

        $settings = AppSetting::first();

        // Fallback to saved password if explicitly empty on UI
        $password = $request->filled('smtp_password') 
            ? $request->smtp_password 
            : ($settings ? $settings->smtp_password : null);

        try {
            // Wipe mailers cache
            Mail::purge('smtp');

            // Temporarily override config for this thread
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $request->smtp_host,
                'mail.mailers.smtp.port' => $request->smtp_port,
                'mail.mailers.smtp.encryption' => $request->smtp_encryption,
                'mail.mailers.smtp.username' => $request->smtp_username,
                'mail.mailers.smtp.password' => $password,
                'mail.from.address' => $request->smtp_from_address,
                'mail.from.name' => $request->smtp_from_name ?: config('mail.from.name'),
            ]);

            Mail::raw("E-Procurement System diagnostics complete.\n\nThis certifies that your live SMTP transport is functioning perfectly.", function ($message) use ($request) {
                $message->to($request->test_email)
                        ->subject("Connection Test: E-Procurement SMTP System");
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email delivered successfully! Please check the inbox of ' . $request->test_email
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
