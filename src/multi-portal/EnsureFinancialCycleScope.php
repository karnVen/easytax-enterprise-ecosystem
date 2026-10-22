<?php

namespace EasyTax\MultiPortal\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class EnsureFinancialCycleScope
 *
 * Prevents multi-tab session contamination across high-throughput tax filing kiosks.
 * Ensures all Eloquent queries and application submissions are strictly bound to an
 * explicit Financial Assessment Year (e.g., FY 2024-25 vs FY 2025-26).
 *
 * @package EasyTax\MultiPortal\Middleware
 * @author karnVen (EasyTax Global IT Solutions)
 */
class EnsureFinancialCycleScope
{
    /**
     * Supported financial cycles for active filing and historical audits.
     */
    protected const VALID_CYCLES = [
        '2024-2025' => ['ay' => '2025-2026', 'is_active' => true],
        '2025-2026' => ['ay' => '2026-2027', 'is_active' => true],
        '2023-2024' => ['ay' => '2024-2025', 'is_active' => false], // Audit mode
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Resolve requested cycle: priority Header > Query Param > Session > Default Active
        $cycleToken = $request->header('X-Financial-Cycle')
            ?? $request->query('cycle')
            ?? session('active_financial_cycle')
            ?? config('easytax.default_cycle', '2025-2026');

        // 2. Validate cycle existence
        if (!array_key_exists($cycleToken, self::VALID_CYCLES)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Invalid or unsupported financial cycle requested.',
                    'supported_cycles' => array_keys(self::VALID_CYCLES)
                ], 422);
            }
            $cycleToken = '2025-2026';
        }

        // 3. Bind to current application container context
        app()->instance('current_financial_cycle', $cycleToken);
        app()->instance('current_assessment_year', self::VALID_CYCLES[$cycleToken]['ay']);

        // 4. Update session for stateful browser navigation
        session(['active_financial_cycle' => $cycleToken]);

        $response = $next($request);

        // 5. Echo back resolved cycle in response headers for client verification
        $response->headers->set('X-Resolved-Financial-Cycle', $cycleToken);

        return $response;
    }
}
