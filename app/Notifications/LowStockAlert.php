<?php

namespace App\Notifications;

use App\Models\ProductVariant;
use App\Services\Bangla;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    public function __construct(public ProductVariant $variant, public int $available) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'low_stock',
            'title' => 'স্টক কমে গেছে: '.$this->variant->sku,
            'body' => sprintf(
                '%s (%s) এর বিক্রয়যোগ্য স্টক কমে %s পিস হয়েছে। সর্বনিম্ন সীমা %s পিস।',
                $this->variant->product->name,
                $this->variant->name,
                Bangla::number($this->available),
                Bangla::number($this->variant->low_stock_threshold),
            ),
            'url' => route('products.show', $this->variant->product_id),
        ];
    }
}
