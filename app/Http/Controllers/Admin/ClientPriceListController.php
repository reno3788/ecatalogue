<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientPriceList;
use App\Models\Company;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClientPriceListController extends Controller
{
    public function index()
    {
        $priceLists = ClientPriceList::with(['company', 'product.categories'])->latest()->get();
        $companies = Company::all();
        $products = Product::where('is_active', true)->get();
        $categories = Category::all();

        return Inertia::render('Admin/ClientPriceLists/Index', [
            'priceLists' => $priceLists,
            'companies' => $companies,
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'product_id' => 'required|exists:products,id',
            'custom_price' => 'required|numeric|min:0',
        ]);

        // Check uniqueness manually for custom error message
        $exists = ClientPriceList::where('company_id', $validated['company_id'])
            ->where('product_id', $validated['product_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors([
                'product_id' => 'A custom price for this product and company combination already exists.',
            ]);
        }

        ClientPriceList::create($validated);

        return redirect()->back()->with('success', 'Custom client price added successfully.');
    }

    public function update(Request $request, $id)
    {
        $priceList = ClientPriceList::findOrFail($id);

        $validated = $request->validate([
            'custom_price' => 'required|numeric|min:0',
        ]);

        $priceList->update($validated);

        return redirect()->back()->with('success', 'Custom client price updated successfully.');
    }

    public function destroy($id)
    {
        $priceList = ClientPriceList::findOrFail($id);
        $priceList->delete();

        return redirect()->back()->with('success', 'Custom client price deleted successfully.');
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="client_price_list_template.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            
            // CSV Header Row
            fputcsv($file, ['company_id', 'company_name', 'product_sku', 'custom_price']);

            // Fetch a couple of sample products and companies to make the template instantly usable and fully populated!
            $sampleCompany = Company::first();
            $sampleProduct = Product::where('is_active', true)->first();

            if ($sampleCompany && $sampleProduct) {
                fputcsv($file, [
                    $sampleCompany->id,
                    $sampleCompany->name,
                    $sampleProduct->sku,
                    round($sampleProduct->base_price * 0.9, 2)
                ]);
            } else {
                // Fallback dummy examples if db is completely empty
                fputcsv($file, [1, 'Sample Company Ltd', 'SKU-SAMPLE-123', 150000]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:4096',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if (!$handle) {
            return redirect()->back()->withErrors(['file' => 'Failed to open the uploaded file.']);
        }

        // Parse headers
        $headerRow = fgetcsv($handle, 4096, ',');
        // If comma fails, fallback to semicolon
        if ($headerRow && count($headerRow) === 1 && str_contains($headerRow[0], ';')) {
            rewind($handle);
            $headerRow = fgetcsv($handle, 4096, ';');
            $delimiter = ';';
        } else {
            $delimiter = ',';
        }

        if (!$headerRow) {
            fclose($handle);
            return redirect()->back()->withErrors(['file' => 'The uploaded CSV file is empty or invalid.']);
        }

        // Map header indices (case-insensitive, trimmed)
        $headers = array_map(function ($h) {
            return strtolower(trim($h));
        }, $headerRow);

        $companyIdIdx = array_search('company_id', $headers);
        $companyNameIdx = array_search('company_name', $headers);
        $productSkuIdx = array_search('product_sku', $headers);
        $customPriceIdx = array_search('custom_price', $headers);

        if ($productSkuIdx === false || $customPriceIdx === false) {
            fclose($handle);
            return redirect()->back()->withErrors([
                'file' => 'Invalid CSV headers. Must contain at least "product_sku" and "custom_price". Optionally "company_id" or "company_name" can be provided.'
            ]);
        }

        $successCount = 0;
        $errors = [];
        $rowNum = 1; // Row 1 is header

        while (($row = fgetcsv($handle, 4096, $delimiter)) !== false) {
            $rowNum++;
            
            // Skip empty rows
            if (empty($row) || (count($row) === 1 && is_null($row[0]))) {
                continue;
            }

            $companyId = $companyIdIdx !== false ? trim($row[$companyIdIdx] ?? '') : '';
            $companyName = $companyNameIdx !== false ? trim($row[$companyNameIdx] ?? '') : '';
            $sku = trim($row[$productSkuIdx] ?? '');
            $priceStr = trim($row[$customPriceIdx] ?? '');

            // 1. Find Company
            $company = null;
            if (!empty($companyId) && is_numeric($companyId)) {
                $company = Company::find($companyId);
            }
            if (!$company && !empty($companyName)) {
                $company = Company::where('name', $companyName)->first();
            }

            if (!$company) {
                $errors[] = "Row {$rowNum}: Company could not be identified (ID: '{$companyId}', Name: '{$companyName}').";
                continue;
            }

            // 2. Find Product
            if (empty($sku)) {
                $errors[] = "Row {$rowNum}: SKU is required.";
                continue;
            }

            $product = Product::where('sku', $sku)->first();
            if (!$product) {
                $errors[] = "Row {$rowNum}: Product with SKU '{$sku}' was not found in the system.";
                continue;
            }

            // 3. Parse Price
            // Strip any currency symbol, commas, or spaces
            $cleanPrice = preg_replace('/[^0-9.]/', '', $priceStr);
            if (!is_numeric($cleanPrice) || $cleanPrice < 0) {
                $errors[] = "Row {$rowNum}: Invalid price value '{$priceStr}'. Must be a non-negative number.";
                continue;
            }

            $customPrice = (float) $cleanPrice;

            // 4. Upsert (Save or Update) Price mapping
            ClientPriceList::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'product_id' => $product->id,
                ],
                [
                    'custom_price' => $customPrice,
                ]
            );

            $successCount++;
        }

        fclose($handle);

        $message = "Successfully uploaded and updated {$successCount} client custom prices.";
        if (count($errors) > 0) {
            $message .= " However, " . count($errors) . " rows were skipped due to errors.";
        }

        return redirect()->back()
            ->with('success', $message)
            ->with('import_errors', $errors);
    }
}
