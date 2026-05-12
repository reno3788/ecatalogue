<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Category;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->get();
        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        $categories = Category::all();
        return Inertia::render('Admin/Categories/Create', [
            'categories' => $categories,
        ]);
    }

    private function getSubtreeHeight($category)
    {
        $maxChildHeight = 0;
        foreach ($category->children as $child) {
            $maxChildHeight = max($maxChildHeight, $this->getSubtreeHeight($child));
        }
        return 1 + $maxChildHeight;
    }

    private function getNodeDepth($category)
    {
        $depth = 1;
        $parent = $category->parent;
        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }
        return $depth;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            $parent = Category::find($parentId);
            if ($parent && $this->getNodeDepth($parent) + 1 > 3) {
                return back()->withErrors(['parent_id' => 'Hierarchy depth cannot exceed 3 levels.']);
            }
        }

        Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'parent_id' => $parentId,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(Category $category)
    {
        $categories = Category::where('id', '!=', $category->id)->get();
        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $parentId = $validated['parent_id'] ?? null;

        // Prevent self-parenting
        if ($parentId == $category->id) {
            return back()->withErrors(['parent_id' => 'A category cannot be its own parent.']);
        }

        // Circular reference check
        if ($parentId) {
            $parent = Category::find($parentId);
            $temp = $parent;
            while ($temp) {
                if ($temp->id === $category->id) {
                    return back()->withErrors(['parent_id' => 'Cannot set a descendant as a parent (circular reference).']);
                }
                $temp = $temp->parent;
            }

            // Depth limit check
            if ($this->getNodeDepth($parent) + $this->getSubtreeHeight($category) > 3) {
                return back()->withErrors(['parent_id' => 'Hierarchy depth cannot exceed 3 levels.']);
            }
        }

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'parent_id' => $parentId,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function updateParent(Request $request, Category $category)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $parentId = $validated['parent_id'] ?? null;

        if ($parentId == $category->id) {
            return response()->json(['error' => 'A category cannot be its own parent.'], 422);
        }

        // Circular reference and depth limit checks
        if ($parentId) {
            $parent = Category::find($parentId);
            $temp = $parent;
            while ($temp) {
                if ($temp->id === $category->id) {
                    return response()->json(['error' => 'Cannot set a descendant as a parent (circular reference).'], 422);
                }
                $temp = $temp->parent;
            }

            // Depth limit check
            if ($this->getNodeDepth($parent) + $this->getSubtreeHeight($category) > 3) {
                return response()->json(['error' => 'Hierarchy depth cannot exceed 3 levels.'], 422);
            }
        }

        $category->update([
            'parent_id' => $parentId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category hierarchy updated successfully.',
            'categories' => Category::withCount('products')->get()
        ]);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }
}
