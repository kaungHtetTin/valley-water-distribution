<?php

namespace App\Services;

use App\Models\StockBalance;
use App\Models\StockMovement;
use Carbon\Carbon;

class InventoryService
{
    public function applyMovement(array $data, Carbon $movementDate, ?int $userId = null, ?string $code = null): array
    {
        $balance = $this->balanceFor((int) $data['warehouse_id'], (int) $data['product_id']);
        $quantity = abs((float) $data['quantity']);
        $signedQuantity = array_key_exists('signed_quantity', $data)
            ? (float) $data['signed_quantity']
            : $this->signedQuantity($data['movement_type'], (float) $data['quantity']);
        $oldQuantity = (float) $balance->quantity;
        $oldAverageCost = (float) $balance->average_cost;
        $requestedCost = array_key_exists('unit_cost', $data) && $data['unit_cost'] !== null
            ? (float) $data['unit_cost']
            : $oldAverageCost;
        $unitCost = $requestedCost;

        if ($signedQuantity < 0) {
            abort_if($oldQuantity < abs($signedQuantity), 409, 'Insufficient stock balance.');
            $unitCost = $requestedCost > 0 ? $requestedCost : $oldAverageCost;
            $newQuantity = $oldQuantity + $signedQuantity;
            $newAverageCost = $oldAverageCost;
        } elseif ($signedQuantity > 0) {
            $newQuantity = $oldQuantity + $signedQuantity;
            $newAverageCost = $newQuantity > 0
                ? (($oldQuantity * $oldAverageCost) + ($signedQuantity * $unitCost)) / $newQuantity
                : $unitCost;
        } else {
            $newQuantity = $oldQuantity;
            $newAverageCost = $oldAverageCost;
        }

        $movement = StockMovement::create([
            'code' => $code ?? $this->nextCode($data['movement_type'], $movementDate),
            'document_code' => $data['document_code'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'],
            'product_id' => $data['product_id'],
            'movement_type' => $data['movement_type'],
            'adjustment_reason' => $data['adjustment_reason'] ?? null,
            'movement_date' => $movementDate->toDateString(),
            'quantity' => $quantity,
            'signed_quantity' => $signedQuantity,
            'balance_before' => $oldQuantity,
            'balance_after' => $newQuantity,
            'unit_cost' => $unitCost,
            'total_cost' => abs($signedQuantity) * $unitCost,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_code' => $data['reference_code'] ?? null,
            'created_by' => $userId,
            'notes' => $data['notes'] ?? null,
        ]);

        $balance->update([
            'quantity' => $newQuantity,
            'average_cost' => $newAverageCost,
            'stock_value' => $newQuantity * $newAverageCost,
            'last_movement_at' => now(),
        ]);

        return [$movement, $balance->refresh()];
    }

    public function balanceFor(int $warehouseId, int $productId): StockBalance
    {
        $balance = StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        return $balance ?: StockBalance::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'quantity' => 0,
            'average_cost' => 0,
            'stock_value' => 0,
        ]);
    }

    private function signedQuantity(string $movementType, float $quantity): float
    {
        return match ($movementType) {
            'issue', 'damage', 'transfer_out', 'delivery_issue' => -abs($quantity),
            'transfer_in', 'delivery_return', 'delivery_issue_reversal' => abs($quantity),
            'delivery_damage', 'sales_return_damage' => 0,
            'adjustment' => $quantity,
            default => abs($quantity),
        };
    }

    private function nextCode(string $movementType, Carbon $movementDate): string
    {
        $prefix = match ($movementType) {
            'opening' => 'OPN',
            'receive' => 'RCV',
            'issue' => 'ISS',
            'damage' => 'DMG',
            'adjustment' => 'ADJ',
            'delivery_issue' => 'DLI',
            'delivery_issue_reversal' => 'DLV',
            'delivery_return' => 'DLR',
            'delivery_damage' => 'DLD',
            'sales_return' => 'SRT',
            'sales_return_damage' => 'SRD',
            default => 'MOV',
        };
        $prefix .= '-'.$movementDate->format('Ym').'-';
        $next = StockMovement::query()->where('code', 'like', "{$prefix}%")->count() + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
