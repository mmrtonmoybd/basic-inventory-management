<?php

namespace App\Services\Inventory;

use App\Enums\MovementType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final readonly class MovementData
{
    /**
     * @param  list<MovementLine>  $lines
     */
    public function __construct(
        public MovementType $type,
        public int $warehouseId,
        public array $lines,
        public CarbonInterface $businessDate,
        public ?User $user = null,
        public ?int $supplierId = null,
        public ?Model $source = null,
        public ?string $externalReference = null,
        public ?string $notes = null,
        public ?string $idempotencyKey = null,
    ) {}
}
