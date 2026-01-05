<?php

declare(strict_types=1);

namespace Haeckel\BasicBcmathCalculator;

use Haeckel\BasicDecimalArithmetic\{
    CalculatorInterface,
    CmpResult,
    DefaultDecimalNum,
    DecimalNumInterface,
    LegacyRoundMode,
};
use Haeckel\TypeWrapper\NonNegativeInt;

class Calculator implements CalculatorInterface
{
    private readonly int $scale;

    public function __construct(
        NonNegativeInt $scale,
        // phpcs:ignore Generic.Files.LineLength.TooLong
        private readonly LegacyRoundMode $roundingMode = LegacyRoundMode::HalfAwayFromZero,
    ) {
        $this->scale = $scale->toInt();
    }

    public function add(
        DecimalNumInterface $augend,
        DecimalNumInterface $addend,
    ): DecimalNumInterface {
        return new DefaultDecimalNum(
            \bcadd($augend->val(), $addend->val(), $this->scale),
        );
    }

    public function sub(
        DecimalNumInterface $minuend,
        DecimalNumInterface $subtrahend,
    ): DecimalNumInterface {
        return new DefaultDecimalNum(
            \bcsub($minuend->val(), $subtrahend->val(), $this->scale),
        );
    }

    public function mul(
        DecimalNumInterface $multiplier,
        DecimalNumInterface $multiplicand,
    ): DecimalNumInterface {
        $res = $this->round(
            \bcmul($multiplier->val(), $multiplicand->val(), $this->scale),
        );
        return new DefaultDecimalNum($res);
    }

    public function div(
        DecimalNumInterface $dividend,
        DecimalNumInterface $divisor,
    ): DecimalNumInterface {
        $res = $this->round(
            \bcdiv($dividend->val(), $divisor->val(), $this->scale + 1)
        );
        return new DefaultDecimalNum($res);
    }

    public function mod(
        DecimalNumInterface $dividend,
        DecimalNumInterface $divisor,
    ): DecimalNumInterface {
        $res = $this->round(
            \bcmod($dividend->val(), $divisor->val(), $this->scale + 1)
        );
        return new DefaultDecimalNum($res);
    }

    public function sum(
        DecimalNumInterface ...$values,
    ): DecimalNumInterface {
        $sum = \bcadd('0', '0', $this->scale); // to ensure proper scale
        foreach ($values as $value) {
            $sum = \bcadd($sum, $value->val(), $this->scale);
        }

        return new DefaultDecimalNum($sum);
    }

    public function diff(
        DecimalNumInterface $minuend,
        DecimalNumInterface ...$subtrahends,
    ): DecimalNumInterface {
         // to ensure proper scale
        $diff = \bcadd($minuend->val(), '0', $this->scale);
        foreach ($subtrahends as $subtrahend) {
            $diff = \bcsub($diff, $subtrahend->val(), $this->scale);
        }

        return new DefaultDecimalNum($diff);
    }

    public function compareTo(
        DecimalNumInterface $lhs,
        DecimalNumInterface $rhs,
    ): CmpResult {
        $res = \bccomp(
            $lhs->val(),
            $rhs->val(),
            $this->scale,
        );
        return CmpResult::fromInt($res);
    }

    /**
     * @param numeric-string&non-empty-string $value
     *
     * @return numeric-string
     */
    private function round(string $value): string
    {
        $withoutDecimalPoint = \str_replace('.', '', $value);
        $strlen = \strlen($withoutDecimalPoint);
        $lastDigit = (int) $withoutDecimalPoint[$strlen - 1];
        $secondLastDigit = (
            $strlen > 1 ? (int) $withoutDecimalPoint[$strlen - 2] : 0
        );

        /**
         * @phpcs:ignore Generic.Files.LineLength.TooLong
         * @var numeric-string&literal-string&lowercase-string&non-falsy-string&uppercase-string $addValue
         */
        $addValue = '0.' . \str_repeat('0', $this->scale) . '5';

        $rounded = match ($this->roundingMode) {
            LegacyRoundMode::HalfAwayFromZero => (
                $lastDigit >= 5
                ? \bcadd($value, $addValue, $this->scale + 1)
                : $value
            ),
            LegacyRoundMode::HalfTowardsZero => (
                $lastDigit > 5
                ? \bcadd($value, $addValue, $this->scale + 1)
                : $value
            ),
            LegacyRoundMode::HalfToEven => $this->roundHalfEven(
                $value,
                $lastDigit,
                $secondLastDigit,
                $addValue,
            ),
            LegacyRoundMode::HalfToOdd => $this->roundHalfOdd(
                $value,
                $lastDigit,
                $secondLastDigit,
                $addValue,
            ),
        };

        // truncate back to desired scale
        return \bcadd($rounded, '0', $this->scale);
    }

    /**
     * @param numeric-string $value
     * @param numeric-string $addValue
     *
     * @return numeric-string
     */
    private function roundHalfEven(
        string $value,
        int $lastDigit,
        int $secondLastDigit,
        string $addValue,
    ): string {
        if ($lastDigit < 5) {
            // truncate will give the correct result
            return $value;
        }
        if ($lastDigit > 5) {
            // must always round up when over half
            return \bcadd($value, $addValue, $this->scale + 1);
        }

        if ($secondLastDigit % 2 === 0) {
            // truncate will give the correct result
            return $value;
        }

        // must round up when exactly half and last retained digit is odd
        return \bcadd($value, $addValue, $this->scale + 1);
    }

    /**
     * @param numeric-string $value
     * @param numeric-string $addValue
     *
     * @return numeric-string
     */
    private function roundHalfOdd(
        string $value,
        int $lastDigit,
        int $secondLastDigit,
        string $addValue,
    ): string {
        if ($lastDigit < 5) {
            // truncate will give the correct result
            return $value;
        }
        if ($lastDigit > 5) {
            // must always round up when over half
            return \bcadd($value, $addValue, $this->scale + 1);
        }

        if ($secondLastDigit % 2 !== 0) {
            // truncate will give the correct result
            return $value;
        }

        // must round up when exactly half and last retained digit is even
        return \bcadd($value, $addValue, $this->scale + 1);
    }
}
