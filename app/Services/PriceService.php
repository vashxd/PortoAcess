<?php

namespace App\Services;

use App\Models\Company;
use App\Models\EntryType;
use App\Models\Price;
use Carbon\CarbonInterface;

class PriceService
{
    /**
     * Preço vigente para tipo de entrada × categoria na data informada.
     */
    public function currentAmount(int $entryTypeId, int $vehicleCategoryId, ?CarbonInterface $date = null): ?float
    {
        $date = $date ?? now();

        $price = Price::where('entry_type_id', $entryTypeId)
            ->where('vehicle_category_id', $vehicleCategoryId)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->orderByDesc('valid_from')
            ->first();

        return $price?->amount !== null ? (float) $price->amount : null;
    }

    /**
     * Valor devido considerando isenção do tipo e desconto de convênio.
     *
     * @return array{amount: float, discount: float}
     */
    public function amountDue(EntryType $entryType, int $vehicleCategoryId, ?Company $company = null): array
    {
        if (! $entryType->is_paid) {
            return ['amount' => 0.0, 'discount' => 0.0];
        }

        $amount = $this->currentAmount($entryType->id, $vehicleCategoryId) ?? 0.0;
        $discount = 0.0;

        if ($company && (float) $company->discount_percent > 0) {
            $discount = round($amount * (float) $company->discount_percent / 100, 2);
        }

        return ['amount' => $amount, 'discount' => $discount];
    }
}
