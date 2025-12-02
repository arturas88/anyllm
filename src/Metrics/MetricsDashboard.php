<?php

declare(strict_types=1);

namespace AnyLLM\Metrics;

final class MetricsDashboard
{
    public function __construct(
        private MetricsCollector $collector,
    ) {}

    /**
     * Generate a text-based dashboard.
     */
    public function render(): string
    {
        $metrics = $this->collector->getMetrics();

        $output = "\n";
        $output .= "╔══════════════════════════════════════════════════════════════╗\n";
        $output .= "║              ANY LLM - METRICS DASHBOARD                     ║\n";
        $output .= "╚══════════════════════════════════════════════════════════════╝\n\n";

        // Requests
        $output .= $this->renderSection("📊 REQUESTS", [
            'Total Requests' => $this->getValue($metrics, 'requests.total'),
            'OpenAI' => $this->getValue($metrics, 'requests.by_provider.openai'),
            'Anthropic' => $this->getValue($metrics, 'requests.by_provider.anthropic'),
            'Google' => $this->getValue($metrics, 'requests.by_provider.google'),
        ]);

        // Latency
        if (isset($metrics['latency.all'])) {
            $latency = $metrics['latency.all'];
            $output .= $this->renderSection("⏱️  LATENCY (ms)", [
                'Average' => number_format($latency['avg'], 2),
                'Min' => number_format($latency['min'], 2),
                'Max' => number_format($latency['max'], 2),
                'P50' => number_format($latency['p50'], 2),
                'P95' => number_format($latency['p95'], 2),
                'P99' => number_format($latency['p99'], 2),
            ]);
        }

        // Tokens
        $output .= $this->renderSection("🎯 TOKENS", [
            'Total Tokens' => number_format($this->getValue($metrics, 'tokens.total')),
            'OpenAI' => number_format($this->getValue($metrics, 'tokens.by_provider.openai')),
            'Anthropic' => number_format($this->getValue($metrics, 'tokens.by_provider.anthropic')),
        ]);

        // Cost
        $output .= $this->renderSection("💰 COST", [
            'Total Cost' => '$' . number_format($this->getValue($metrics, 'cost.total'), 4),
            'OpenAI' => '$' . number_format($this->getValue($metrics, 'cost.by_provider.openai'), 4),
            'Anthropic' => '$' . number_format($this->getValue($metrics, 'cost.by_provider.anthropic'), 4),
        ]);

        // Errors
        $totalRequests = $this->getValue($metrics, 'requests.total');
        $totalErrors = $this->getValue($metrics, 'errors.total');
        $errorRate = $totalRequests > 0 ? ($totalErrors / $totalRequests) * 100 : 0;

        $output .= $this->renderSection("❌ ERRORS", [
            'Total Errors' => $totalErrors,
            'Error Rate' => number_format($errorRate, 2) . '%',
            'Auth Errors' => $this->getValue($metrics, 'errors.by_type.auth'),
            'Rate Limit' => $this->getValue($metrics, 'errors.by_type.rate_limit'),
        ]);

        // Cache
        $cacheHits = $this->getValue($metrics, 'cache.hits');
        $cacheMisses = $this->getValue($metrics, 'cache.misses');
        $cacheTotal = $cacheHits + $cacheMisses;
        $cacheHitRate = $cacheTotal > 0 ? ($cacheHits / $cacheTotal) * 100 : 0;

        $output .= $this->renderSection("⚡ CACHE", [
            'Hit Rate' => number_format($cacheHitRate, 2) . '%',
            'Hits' => $cacheHits,
            'Misses' => $cacheMisses,
        ]);

        $output .= "\n";

        return $output;
    }

    /**
     * Render a section.
     */
    private function renderSection(string $title, array $data): string
    {
        $output = "{$title}\n";
        $output .= str_repeat('─', 60) . "\n";

        foreach ($data as $label => $value) {
            $output .= sprintf("  %-25s : %s\n", $label, $value);
        }

        $output .= "\n";

        return $output;
    }

    /**
     * Get metric value safely.
     */
    private function getValue(array $metrics, string $key): int|float
    {
        if (! isset($metrics[$key])) {
            return 0;
        }

        return $metrics[$key]['value'] ?? 0;
    }

    /**
     * Generate HTML dashboard.
     */
    public function renderHtml(): string
    {
        $metrics = $this->collector->getMetrics();
        $jsonMetrics = json_encode($metrics, JSON_PRETTY_PRINT);

        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <title>AnyLLM Metrics Dashboard</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; background: #f5f5f5; }
                    .container { max-width: 1200px; margin: 0 auto; }
                    h1 { color: #333; }
                    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
                    .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                    .card h2 { margin-top: 0; color: #666; font-size: 14px; text-transform: uppercase; }
                    .metric-value { font-size: 32px; font-weight: bold; color: #333; margin: 10px 0; }
                    .metric-label { font-size: 12px; color: #999; }
                    .progress-bar { width: 100%; height: 8px; background: #eee; border-radius: 4px; overflow: hidden; margin: 10px 0; }
                    .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); }
                    pre { background: #f8f8f8; padding: 15px; border-radius: 4px; overflow-x: auto; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>📊 AnyLLM Metrics Dashboard</h1>
                    
                    <div class="grid">
                        <div class="card">
                            <h2>Total Requests</h2>
                            <div class="metric-value">{$this->getValue($metrics, 'requests.total')}</div>
                        </div>
                        
                        <div class="card">
                            <h2>Total Tokens</h2>
                            <div class="metric-value">{$this->formatNumber($this->getValue($metrics, 'tokens.total'))}</div>
                        </div>
                        
                        <div class="card">
                            <h2>Total Cost</h2>
                            <div class="metric-value">\${$this->formatMoney($this->getValue($metrics, 'cost.total'))}</div>
                        </div>
                        
                        <div class="card">
                            <h2>Error Rate</h2>
                            <div class="metric-value">{$this->formatPercent($this->getErrorRate($metrics))}%</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: {$this->getErrorRate($metrics)}%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h2>Raw Metrics (JSON)</h2>
                        <pre>{$jsonMetrics}</pre>
                    </div>
                </div>
            </body>
            </html>
            HTML;
    }

    private function formatNumber(int|float $num): string
    {
        return number_format($num);
    }

    private function formatMoney(float $num): string
    {
        return number_format($num, 4);
    }

    private function formatPercent(float $num): string
    {
        return number_format($num, 2);
    }

    private function getErrorRate(array $metrics): float
    {
        $total = $this->getValue($metrics, 'requests.total');
        $errors = $this->getValue($metrics, 'errors.total');
        return $total > 0 ? ($errors / $total) * 100 : 0;
    }
}
