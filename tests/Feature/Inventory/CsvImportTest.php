<?php

use App\Enums\Role;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;

function csvUpload(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('import.csv', $contents);
}

it('imports products, variants and opening stock from a valid file', function () {
    makeProduct(['Size' => ['L', 'XL'], 'Color' => ['Red', 'Black']], [['L', 'Red']]);
    $warehouse = defaultWarehouse();

    actingAs(userWithRole(Role::InventoryManager))->post(route('import.store'), [
        'warehouse_id' => $warehouse->id,
        'commit' => true,
        'file' => csvUpload("product_code,product_name,category,attributes,sku,low_stock_threshold,opening_quantity\nTEE9,Tee,Tops,Size:L|Color:Red,,5,10\nTEE9,Tee,Tops,Size:XL|Color:Black,TEE9-XLB,5,4\nMUG1,Mug,,,,0,7\n"),
    ])->assertRedirect();

    $tee = Product::query()->where('code', 'TEE9')->sole();
    expect($tee->variants()->count())->toBe(2)
        ->and(balanceOf(variantNamed($tee, 'XL / Black'))->available)->toBe(4)
        ->and(balanceOf(Product::query()->where('code', 'MUG1')->sole()->variants()->sole())->available)->toBe(7);
});

it('writes nothing when any row is invalid', function () {
    makeProduct(['Size' => ['L']], [['L']]);

    actingAs(userWithRole(Role::InventoryManager))->post(route('import.store'), [
        'warehouse_id' => defaultWarehouse()->id,
        'commit' => true,
        'file' => csvUpload("product_code,product_name,category,attributes,sku,low_stock_threshold,opening_quantity\nNEW1,Ok,,Size:L,,0,5\nNEW2,Bad,,Size:Huge,,0,5\n"),
    ])->assertRedirect();

    expect(Product::query()->whereIn('code', ['NEW1', 'NEW2'])->exists())->toBeFalse()
        ->and(StockMovement::query()->count())->toBe(0);
});
