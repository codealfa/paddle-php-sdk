<?php

declare(strict_types=1);

namespace Paddle\SDK\Notifications\Entities\Shared;

/**
 * A price's trial period. A trial with a unit price is paid; one without is free.
 */
class PriceTrialPeriod extends TimePeriod
{
    /**
     * @param array<UnitPriceOverride> $unitPriceOverrides
     */
    private function __construct(
        Interval $interval,
        int $frequency,
        public bool $requiresPaymentMethod,
        public Money|null $unitPrice,
        public array $unitPriceOverrides,
    ) {
        $this->interval = $interval;
        $this->frequency = $frequency;
    }

    public static function from(array $data): self
    {
        return new self(
            interval: Interval::from($data['interval']),
            frequency: $data['frequency'],
            requiresPaymentMethod: $data['requires_payment_method'] ?? true,
            unitPrice: isset($data['unit_price']) ? Money::from($data['unit_price']) : null,
            unitPriceOverrides: array_map(
                fn (array $override): UnitPriceOverride => UnitPriceOverride::from($override),
                $data['unit_price_overrides'] ?? [],
            ),
        );
    }
}
