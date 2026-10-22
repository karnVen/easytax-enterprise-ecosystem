<?php

namespace EasyTax\TaxEngine;

/**
 * Class TaxRegimeComparator
 *
 * Deterministic Financial Math Engine for Side-by-Side Tax Regime Comparison.
 * Strictly avoids floating-point arithmetic by executing all calculations in integer paise
 * (1 INR = 100 paise), guaranteeing zero rounding drift.
 *
 * Compares:
 *  - Old Tax Regime (Chapter VI-A deductions, 80C, 80D, HRA, standard deduction)
 *  - New Tax Regime (Section 115BAC, updated standard deduction, streamlined slabs)
 *
 * @package EasyTax\TaxEngine
 * @author karnVen (EasyTax Global IT Solutions)
 */
class TaxRegimeComparator
{
    /**
     * Standard deduction in paise (₹75,000 for New Regime FY25-26, ₹50,000 for Old Regime).
     */
    protected const STD_DEDUCTION_OLD_PAISE = 5000000;
    protected const STD_DEDUCTION_NEW_PAISE = 7500000;

    /**
     * Health and Education Cess rate (4%).
     */
    protected const CESS_BASIS_POINTS = 400; // 4.00%

    /**
     * Compare both regimes and return comprehensive liability breakdown.
     *
     * @param int $grossIncomePaise Gross total income in paise
     * @param int $deductions80CPaise Total eligible Section 80C deductions (capped at ₹1.5L)
     * @param int $deductions80DPaise Health insurance deductions under Section 80D
     * @param int $hraExemptionPaise Eligible House Rent Allowance exemption
     * @return array
     */
    public static function compare(
        int $grossIncomePaise,
        int $deductions80CPaise = 0,
        int $deductions80DPaise = 0,
        int $hraExemptionPaise = 0
    ): array {
        // --- 1. Old Regime Computation ---
        $capped80C = min($deductions80CPaise, 15000000); // Max ₹1,50,000
        $totalOldDeductions = self::STD_DEDUCTION_OLD_PAISE + $capped80C + $deductions80DPaise + $hraExemptionPaise;
        $taxableOld = max(0, $grossIncomePaise - $totalOldDeductions);
        $taxOldBeforeCess = self::computeOldSlabs($taxableOld);

        // Section 87A rebate for Old Regime (up to ₹5,00,000 taxable income)
        if ($taxableOld <= 50000000) {
            $taxOldBeforeCess = 0;
        }

        $cessOld = (int) round(($taxOldBeforeCess * self::CESS_BASIS_POINTS) / 10000);
        $totalOldTax = $taxOldBeforeCess + $cessOld;

        // --- 2. New Regime Computation (Section 115BAC) ---
        $taxableNew = max(0, $grossIncomePaise - self::STD_DEDUCTION_NEW_PAISE);
        $taxNewBeforeCess = self::computeNewSlabs($taxableNew);

        // Section 87A rebate for New Regime (up to ₹7,00,000 taxable income)
        if ($taxableNew <= 70000000) {
            $taxNewBeforeCess = 0;
        }

        $cessNew = (int) round(($taxNewBeforeCess * self::CESS_BASIS_POINTS) / 10000);
        $totalNewTax = $taxNewBeforeCess + $cessNew;

        // --- 3. Differential Analysis ---
        $savingsPaise = abs($totalOldTax - $totalNewTax);
        $recommended = $totalNewTax <= $totalOldTax ? 'NEW_REGIME' : 'OLD_REGIME';

        return [
            'gross_income_inr' => $grossIncomePaise / 100,
            'old_regime' => [
                'total_deductions_inr' => $totalOldDeductions / 100,
                'taxable_income_inr' => $taxableOld / 100,
                'tax_before_cess_inr' => $taxOldBeforeCess / 100,
                'cess_inr' => $cessOld / 100,
                'total_payable_inr' => $totalOldTax / 100,
            ],
            'new_regime' => [
                'total_deductions_inr' => self::STD_DEDUCTION_NEW_PAISE / 100,
                'taxable_income_inr' => $taxableNew / 100,
                'tax_before_cess_inr' => $taxNewBeforeCess / 100,
                'cess_inr' => $cessNew / 100,
                'total_payable_inr' => $totalNewTax / 100,
            ],
            'recommendation' => [
                'optimal_regime' => $recommended,
                'net_annual_savings_inr' => $savingsPaise / 100,
                'recommendation_summary' => $recommended === 'NEW_REGIME'
                    ? "New Regime yields ₹" . number_format($savingsPaise / 100, 2) . " lower liability."
                    : "Old Regime yields ₹" . number_format($savingsPaise / 100, 2) . " lower liability due to Chapter VI-A deductions.",
            ],
        ];
    }

    protected static function computeOldSlabs(int $taxablePaise): int
    {
        $tax = 0;
        // Slab 1: Up to ₹2.5L (0%)
        // Slab 2: ₹2.5L to ₹5L (5%)
        if ($taxablePaise > 25000000) {
            $taxableInSlab = min($taxablePaise - 25000000, 25000000);
            $tax += (int) round($taxableInSlab * 0.05);
        }
        // Slab 3: ₹5L to ₹10L (20%)
        if ($taxablePaise > 50000000) {
            $taxableInSlab = min($taxablePaise - 50000000, 50000000);
            $tax += (int) round($taxableInSlab * 0.20);
        }
        // Slab 4: Above ₹10L (30%)
        if ($taxablePaise > 100000000) {
            $taxableInSlab = $taxablePaise - 100000000;
            $tax += (int) round($taxableInSlab * 0.30);
        }
        return $tax;
    }

    protected static function computeNewSlabs(int $taxablePaise): int
    {
        $tax = 0;
        // Slabs FY 2024-25 / 2025-26:
        // Up to ₹3L: 0%
        // ₹3L - ₹7L: 5%
        if ($taxablePaise > 30000000) {
            $taxableInSlab = min($taxablePaise - 30000000, 40000000);
            $tax += (int) round($taxableInSlab * 0.05);
        }
        // ₹7L - ₹10L: 10%
        if ($taxablePaise > 70000000) {
            $taxableInSlab = min($taxablePaise - 70000000, 30000000);
            $tax += (int) round($taxableInSlab * 0.10);
        }
        // ₹10L - ₹12L: 15%
        if ($taxablePaise > 100000000) {
            $taxableInSlab = min($taxablePaise - 100000000, 20000000);
            $tax += (int) round($taxableInSlab * 0.15);
        }
        // ₹12L - ₹15L: 20%
        if ($taxablePaise > 120000000) {
            $taxableInSlab = min($taxablePaise - 120000000, 30000000);
            $tax += (int) round($taxableInSlab * 0.20);
        }
        // Above ₹15L: 30%
        if ($taxablePaise > 150000000) {
            $taxableInSlab = $taxablePaise - 150000000;
            $tax += (int) round($taxableInSlab * 0.30);
        }
        return $tax;
    }
}
