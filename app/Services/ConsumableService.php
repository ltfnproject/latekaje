<?php

namespace App\Services;

use App\Models\Consumable;
use App\Models\ConsumableTransaction;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumableService
{
    /**
     * Catat transaksi masuk / keluar / penyesuaian stok Bahan Habis Pakai secara atomik.
     *
     * @param  array{funding_source_id?: int|null, unit_price?: int|null, member_id?: int|null, recipient_name?: string|null, recipient_group?: string|null, purpose?: string|null}  $meta
     *
     * @throws ValidationException
     */
    public static function recordTransaction(
        Consumable $consumable,
        string $type,
        int $quantity,
        array $meta = [],
    ): ConsumableTransaction {
        if (! in_array($type, [ConsumableTransaction::TYPE_IN, ConsumableTransaction::TYPE_OUT, ConsumableTransaction::TYPE_ADJUSTMENT], true)) {
            throw ValidationException::withMessages([
                'type' => 'Tipe transaksi tidak valid.',
            ]);
        }

        if ($type === ConsumableTransaction::TYPE_ADJUSTMENT) {
            if ($quantity < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok koreksi tidak boleh bernilai negatif.',
                ]);
            }
        } elseif ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah harus lebih dari 0.',
            ]);
        }

        return DB::transaction(function () use ($consumable, $type, $quantity, $meta): ConsumableTransaction {
            /** @var Consumable $locked */
            $locked = Consumable::lockForUpdate()->findOrFail($consumable->getKey());

            $before = (int) $locked->stock;

            if ($type === ConsumableTransaction::TYPE_OUT && $before < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Stok {$locked->name} tidak mencukupi (tersisa {$before} {$locked->unit}).",
                ]);
            }

            $after = match ($type) {
                ConsumableTransaction::TYPE_IN => $before + $quantity,
                ConsumableTransaction::TYPE_OUT => $before - $quantity,
                ConsumableTransaction::TYPE_ADJUSTMENT => $quantity,
            };

            $recipientName = filled($meta['recipient_name'] ?? null) ? trim((string) $meta['recipient_name']) : null;
            $recipientGroup = filled($meta['recipient_group'] ?? null) ? trim((string) $meta['recipient_group']) : null;
            $memberId = filled($meta['member_id'] ?? null) ? (int) $meta['member_id'] : null;

            if ($memberId && ($member = Member::find($memberId))) {
                $recipientName = $recipientName ?: $member->name;
                $recipientGroup = $recipientGroup ?: $member->group;
            }

            $locked->update(['stock' => $after]);

            $tx = ConsumableTransaction::create([
                'consumable_id' => $locked->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'funding_source_id' => filled($meta['funding_source_id'] ?? null) ? (int) $meta['funding_source_id'] : null,
                'unit_price' => filled($meta['unit_price'] ?? null) ? (int) $meta['unit_price'] : null,
                'member_id' => $memberId,
                'recipient_name' => $recipientName,
                'recipient_group' => $recipientGroup,
                'purpose' => filled($meta['purpose'] ?? null) ? trim((string) $meta['purpose']) : null,
                'recorded_by' => auth()->id(),
                'transacted_at' => now(),
            ]);

            $action = match ($type) {
                ConsumableTransaction::TYPE_IN => 'consumable.restocked',
                ConsumableTransaction::TYPE_OUT => 'consumable.used',
                default => 'consumable.adjusted',
            };

            $desc = match ($type) {
                ConsumableTransaction::TYPE_IN => "Restock {$locked->name} +{$quantity} {$locked->unit} (stok: {$before} → {$after})",
                ConsumableTransaction::TYPE_OUT => "Pemakaian {$locked->name} -{$quantity} {$locked->unit}".($recipientName ? " oleh {$recipientName}" : '').($recipientGroup ? " ({$recipientGroup})" : '')." (sisa: {$after})",
                default => "Koreksi stok {$locked->name}: {$before} → {$after} {$locked->unit}",
            };

            ActivityLogger::log(
                $action,
                $desc,
                $locked,
                [
                    'consumable' => $locked->name,
                    'type' => $type,
                    'quantity' => $quantity,
                    'unit' => $locked->unit,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'recipient' => $recipientName,
                    'group' => $recipientGroup,
                    'purpose' => $tx->purpose,
                ],
            );

            return $tx;
        });
    }
}
