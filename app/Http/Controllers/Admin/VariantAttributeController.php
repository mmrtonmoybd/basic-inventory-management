<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VariantAttribute;
use App\Models\VariantAttributeValue;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VariantAttributeController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(): Response
    {
        $usage = DB::table('product_variant_attribute_values')
            ->selectRaw('variant_attribute_value_id, COUNT(*) as total')
            ->groupBy('variant_attribute_value_id')
            ->pluck('total', 'variant_attribute_value_id');

        $attributes = VariantAttribute::query()
            ->with('values')
            ->withCount(['values'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (VariantAttribute $attribute): array => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'is_color' => $attribute->is_color,
                'sort_order' => $attribute->sort_order,
                'products_count' => DB::table('product_attributes')->where('variant_attribute_id', $attribute->id)->count(),
                'values' => $attribute->values->map(fn (VariantAttributeValue $value): array => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'code' => $value->code,
                    'swatch' => $value->swatch,
                    'sort_order' => $value->sort_order,
                    'variants_count' => (int) ($usage[$value->id] ?? 0),
                ]),
            ]);

        return Inertia::render('admin/attributes', ['attributes' => $attributes]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:variant_attributes,name'],
            'is_color' => ['boolean'],
        ]);

        $attribute = VariantAttribute::query()->create([
            ...$data,
            'slug' => Str::slug($data['name']),
            'sort_order' => (int) VariantAttribute::query()->max('sort_order') + 1,
        ]);

        $this->activity->log('attribute.created', "নতুন বৈশিষ্ট্য {$attribute->name} যোগ হয়েছে");
        $this->toast("বৈশিষ্ট্য {$attribute->name} যোগ হয়েছে।");

        return back();
    }

    public function update(Request $request, VariantAttribute $attribute): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', Rule::unique('variant_attributes', 'name')->ignore($attribute)],
            'is_color' => ['boolean'],
        ]);

        $attribute->update($data);
        $this->toast('বৈশিষ্ট্য সেভ হয়েছে।');

        return back();
    }

    public function storeValue(Request $request, VariantAttribute $attribute): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:64', Rule::unique('variant_attribute_values', 'value')->where('variant_attribute_id', $attribute->id)],
            'code' => ['nullable', 'string', 'max:32', 'alpha_dash'],
            'swatch' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $attribute->values()->create([...$data, 'sort_order' => (int) $attribute->values()->max('sort_order') + 1]);

        $this->activity->log('attribute.value_added', "{$attribute->name} এ {$data['value']} যোগ হয়েছে");
        $this->toast("{$attribute->name} এ {$data['value']} যোগ হয়েছে।");

        return back();
    }

    public function updateValue(Request $request, VariantAttributeValue $value): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:64', Rule::unique('variant_attribute_values', 'value')->where('variant_attribute_id', $value->variant_attribute_id)->ignore($value)],
            'code' => ['nullable', 'string', 'max:32', 'alpha_dash'],
            'swatch' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $value->update($data);
        $this->toast('মান সেভ হয়েছে।');

        return back();
    }

    public function destroyValue(VariantAttributeValue $value): RedirectResponse
    {
        if (DB::table('product_variant_attribute_values')->where('variant_attribute_value_id', $value->id)->exists()) {
            throw ValidationException::withMessages(['value' => "{$value->value} কিছু ভ্যারিয়েন্টে ব্যবহার হচ্ছে, তাই মোছা যাবে না।"]);
        }

        $value->delete();
        $this->toast('মান মুছে ফেলা হয়েছে।');

        return back();
    }
}
