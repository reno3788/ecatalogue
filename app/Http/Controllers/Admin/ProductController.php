<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\Category;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('categories')->get();
        $categories = Category::all();

        return Inertia::render('Admin/Products/Index', [
            'products'   => $products,
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Products/Create', [
            'categories' => Category::all(),
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('Store request received:', $request->all());
        \Log::info('Store files received:', $request->allFiles());

        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'sku'                  => 'required|string|unique:products',
            'base_price'           => 'required|numeric|min:0',
            'minimum_order'        => 'nullable|integer|min:1',
            'brand'                => 'nullable|string',
            'weight'               => 'nullable|numeric|min:0',
            'color'                => 'nullable|string|max:255',
            'size'                 => 'nullable|string|max:255',
            'description'          => 'nullable|string',
            'uom'                  => 'nullable|string|max:255',
            'classification'       => 'nullable|string|max:255',
            'manufacturer_part_id' => 'nullable|string|max:255',
            'manufacturer_name'    => 'nullable|string|max:255',
            'categories'           => 'array',
            'categories.*'         => 'exists:categories,id',
            'images'               => 'nullable|array',
            'images.*'             => 'image|max:2048',
            'primary_image_index'  => 'nullable|integer',
        ]);

        \Log::info('Store validation passed:', $validated);

        $productData = $validated;
        unset($productData['images'], $productData['primary_image_index']);

        if ($request->hasFile('images')) {
            $primaryIndex = $request->input('primary_image_index', 0);
            $files = $request->file('images');
            \Log::info('Storing primary image. Index: ' . $primaryIndex);

            if (isset($files[$primaryIndex])) {
                $productData['image'] = '/storage/' . $files[$primaryIndex]->store('products', 'public');
                \Log::info('Saved primary image to: ' . $productData['image']);
            } else if (count($files) > 0) {
                $productData['image'] = '/storage/' . $files[0]->store('products', 'public');
                \Log::info('Saved fallback primary image to: ' . $productData['image']);
            }
        }

        $product = Product::create($productData);

        if ($request->has('categories')) {
            $product->categories()->sync($request->categories);
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $file) {
                if ((int)$index === (int)$request->input('primary_image_index', 0)) {
                    continue;
                }
                $product->images()->create([
                    'image_path' => '/storage/' . $file->store('products', 'public'),
                    'is_primary' => false,
                ]);
            }
        }

        return redirect()->route('admin.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $product->load('categories', 'images');
        return Inertia::render('Admin/Products/Edit', [
            'product'    => $product,
            'categories' => Category::all(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        \Log::info('Update request received for product ' . $product->id . ':', $request->all());
        \Log::info('Update files received:', $request->allFiles());

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'sku'               => 'required|string|unique:products,sku,' . $product->id,
            'base_price'        => 'required|numeric|min:0',
            'minimum_order'     => 'nullable|integer|min:1',
            'brand'             => 'nullable|string',
            'weight'            => 'nullable|numeric|min:0',
            'color'             => 'nullable|string|max:255',
            'size'              => 'nullable|string|max:255',
            'description'       => 'nullable|string',
            'uom'               => 'nullable|string|max:255',
            'classification'    => 'nullable|string|max:255',
            'manufacturer_part_id'=> 'nullable|string|max:255',
            'manufacturer_name' => 'nullable|string|max:255',
            'categories'        => 'array',
            'categories.*'      => 'exists:categories,id',
            'images'            => 'nullable|array',
            'images.*'          => 'image|max:2048',
            'deleted_existing'  => 'nullable|array',
            'deleted_existing.*'=> 'integer',
            'deleted_main'      => 'nullable|boolean',
            'primary_type'      => 'nullable|string|in:main,existing,new',
            'primary_value'     => 'nullable',
        ]);

        \Log::info('Update validation passed:', $validated);

        $productData = $validated;
        unset($productData['images'], $productData['deleted_existing'], $productData['deleted_main'], $productData['primary_type'], $productData['primary_value']);

        if ($request->input('deleted_main')) {
            $productData['image'] = null;
        }

        if ($request->has('deleted_existing')) {
            $product->images()->whereIn('id', $request->deleted_existing)->delete();
        }

        $newFiles    = $request->file('images') ?? [];
        $savedNewFiles = [];

        foreach ($newFiles as $index => $file) {
            $savedNewFiles[$index] = '/storage/' . $file->store('products', 'public');
        }

        $primaryType  = $request->input('primary_type', 'main');
        $primaryValue = $request->input('primary_value');
        $newPrimaryUrl = $productData['image'] ?? $product->image;

        if ($primaryType === 'new' && isset($savedNewFiles[$primaryValue])) {
            $newPrimaryUrl = $savedNewFiles[$primaryValue];
        } else if ($primaryType === 'existing') {
            $existingImg = $product->images()->find($primaryValue);
            if ($existingImg) {
                $newPrimaryUrl = $existingImg->image_path;
                $existingImg->delete();
            }
        } else if ($primaryType === 'main' && $request->input('deleted_main')) {
            if (count($savedNewFiles) > 0) {
                $newPrimaryUrl = $savedNewFiles[0];
                $primaryType   = 'new';
                $primaryValue  = 0;
            } else if ($product->images()->count() > 0) {
                $firstExisting = $product->images()->first();
                $newPrimaryUrl = $firstExisting->image_path;
                $firstExisting->delete();
            }
        }

        $productData['image'] = $newPrimaryUrl;

        foreach ($savedNewFiles as $index => $path) {
            if ($primaryType === 'new' && (string)$primaryValue === (string)$index) {
                continue;
            }
            $product->images()->create([
                'image_path' => $path,
                'is_primary' => false,
            ]);
        }

        $product->update($productData);

        if ($request->has('categories')) {
            $product->categories()->sync($request->categories);
        }

        return redirect()->route('admin.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Product deleted successfully.');
    }

    // ─── CSV Template Download ────────────────────────────────────────────────

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products_import_template.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // Header row — categories are pipe-separated category names e.g. "Electronics|Laptops"
            fputcsv($file, ['name', 'sku', 'base_price', 'minimum_order', 'brand', 'description', 'categories', 'weight', 'color', 'size', 'uom', 'classification', 'manufacturer_part_id', 'manufacturer_name']);

            // Sample row using first active product, or a fallback
            $sample = Product::with('categories')->where('is_active', true)->first();
            if ($sample) {
                $catNames = $sample->categories->pluck('name')->implode('|');
                fputcsv($file, [
                    $sample->name,
                    $sample->sku,
                    $sample->base_price,
                    $sample->minimum_order ?? 1,
                    $sample->brand ?? '',
                    $sample->description ?? '',
                    $catNames,
                    $sample->weight ?? '',
                    $sample->color ?? '',
                    $sample->size ?? '',
                    $sample->uom ?? '',
                    $sample->classification ?? '',
                    $sample->manufacturer_part_id ?? '',
                    $sample->manufacturer_name ?? '',
                ]);
            } else {
                fputcsv($file, ['Sample Product', 'SKU-EXAMPLE-001', 100000, 1, 'BrandName', 'Product description here', 'Electronics|Laptops', 1.5, '#FF0000', 'L', 'EA', 'UNSPSC-10101502', 'MFG-PART-XYZ', 'Global Mfg Corp']);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─── Bulk CSV Upload / Upsert ─────────────────────────────────────────────

    public function uploadBulk(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:4096',
        ]);

        $path   = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if (!$handle) {
            return redirect()->back()->withErrors(['file' => 'Failed to open the uploaded file.']);
        }

        // Auto-detect delimiter (comma or semicolon)
        $headerRow = fgetcsv($handle, 4096, ',');
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

        $headers = array_map(fn($h) => strtolower(trim($h)), $headerRow);

        $nameIdx  = array_search('name', $headers);
        $skuIdx   = array_search('sku', $headers);
        $priceIdx = array_search('base_price', $headers);

        if ($skuIdx === false || $priceIdx === false || $nameIdx === false) {
            fclose($handle);
            return redirect()->back()->withErrors([
                'file' => 'CSV must contain at minimum: name, sku, base_price columns.',
            ]);
        }

        $minOrderIdx = array_search('minimum_order', $headers);
        $brandIdx = array_search('brand', $headers);
        $descIdx  = array_search('description', $headers);
        $catIdx   = array_search('categories', $headers);
        $weightIdx = array_search('weight', $headers);
        $colorIdx  = array_search('color', $headers);
        $sizeIdx   = array_search('size', $headers);
        $uomIdx    = array_search('uom', $headers);
        $classIdx  = array_search('classification', $headers);
        $mfgPartIdx = array_search('manufacturer_part_id', $headers);
        $mfgNameIdx = array_search('manufacturer_name', $headers);

        // Pre-load all categories keyed by lowercase name for fast lookup
        $allCategories = Category::all()->keyBy(fn($c) => strtolower(trim($c->name)));

        $successCount = 0;
        $errors       = [];
        $rowNum       = 1;

        DB::transaction(function () use (
            $handle, $delimiter, $nameIdx, $skuIdx, $priceIdx, $minOrderIdx, $brandIdx, $descIdx, $catIdx,
            $weightIdx, $colorIdx, $sizeIdx, $uomIdx, $classIdx, $mfgPartIdx, $mfgNameIdx,
            $allCategories, &$successCount, &$errors, &$rowNum
        ) {
            while (($row = fgetcsv($handle, 4096, $delimiter)) !== false) {
                $rowNum++;

                if (empty($row) || (count($row) === 1 && is_null($row[0]))) {
                    continue;
                }

                $sku  = trim($row[$skuIdx] ?? '');
                $name = trim($row[$nameIdx] ?? '');

                if (empty($sku)) {
                    $errors[] = "Row {$rowNum}: SKU is required.";
                    continue;
                }
                if (empty($name)) {
                    $errors[] = "Row {$rowNum}: Name is required.";
                    continue;
                }

                $rawPrice   = trim($row[$priceIdx] ?? '');
                $cleanPrice = preg_replace('/[^0-9.]/', '', $rawPrice);
                if (!is_numeric($cleanPrice) || (float)$cleanPrice < 0) {
                    $errors[] = "Row {$rowNum}: Invalid base_price '{$rawPrice}'. Must be a non-negative number.";
                    continue;
                }

                $rawWeight = $weightIdx !== false ? trim($row[$weightIdx] ?? '') : '';
                $weightVal = is_numeric($rawWeight) ? (float)$rawWeight : null;

                $rawMinOrder = $minOrderIdx !== false ? trim($row[$minOrderIdx] ?? '') : '';
                $minOrderVal = is_numeric($rawMinOrder) && (int)$rawMinOrder > 0 ? (int)$rawMinOrder : 1;

                $data = [
                    'name'                 => $name,
                    'base_price'           => (float)$cleanPrice,
                    'minimum_order'        => $minOrderVal,
                    'brand'                => $brandIdx !== false ? (trim($row[$brandIdx] ?? '') ?: null) : null,
                    'description'          => $descIdx  !== false ? (trim($row[$descIdx]  ?? '') ?: null) : null,
                    'weight'               => $weightVal,
                    'color'                => $colorIdx  !== false ? (trim($row[$colorIdx] ?? '') ?: null) : null,
                    'size'                 => $sizeIdx   !== false ? (trim($row[$sizeIdx]  ?? '') ?: null) : null,
                    'uom'                  => $uomIdx !== false ? (trim($row[$uomIdx] ?? '') ?: null) : null,
                    'classification'       => $classIdx !== false ? (trim($row[$classIdx] ?? '') ?: null) : null,
                    'manufacturer_part_id' => $mfgPartIdx !== false ? (trim($row[$mfgPartIdx] ?? '') ?: null) : null,
                    'manufacturer_name'    => $mfgNameIdx !== false ? (trim($row[$mfgNameIdx] ?? '') ?: null) : null,
                ];

                $product = Product::updateOrCreate(['sku' => $sku], $data);

                // Sync categories if the column is present
                if ($catIdx !== false) {
                    $rawCats  = trim($row[$catIdx] ?? '');
                    $catNames = array_filter(array_map('trim', explode('|', $rawCats)));
                    $catIds   = [];
                    foreach ($catNames as $catName) {
                        $matched = $allCategories->get(strtolower($catName));
                        if ($matched) {
                            $catIds[] = $matched->id;
                        }
                    }
                    if (!empty($catIds)) {
                        $product->categories()->sync($catIds);
                    }
                }

                $successCount++;
            }
        });

        fclose($handle);

        $message = "Successfully imported {$successCount} product(s).";
        if (count($errors) > 0) {
            $message .= ' ' . count($errors) . ' row(s) were skipped.';
        }

        return redirect()->back()
            ->with('success', $message)
            ->with('import_errors', $errors);
    }
}

