<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/categories', [
            'categories' => Category::query()->with('parent:id,name')->withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Category::query()->create([...$data, 'slug' => $this->uniqueSlug($data['name'])]);
        $this->toast("ক্যাটাগরি {$data['name']} যোগ হয়েছে।");

        return back();
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);

        if (($data['parent_id'] ?? null) === $category->id) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent.']);
        }

        $category->update($data);
        $this->toast('ক্যাটাগরি সেভ হয়েছে।');

        return back();
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages(['category' => 'এই ক্যাটাগরিতে পণ্য আছে। মুছার আগে পণ্যগুলো অন্য ক্যাটাগরিতে সরান।']);
        }

        $category->delete();
        $this->toast('ক্যাটাগরি মুছে ফেলা হয়েছে।');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $candidate = $slug;
        $suffix = 2;

        while (Category::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
