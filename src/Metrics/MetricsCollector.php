<?php

declare(strict_types=1);

namespace AnyLLM\Metrics;

final class MetricsCollector
{
    private array $metrics = [];
    private array $timers = [];

    /**
     * Record a request.
     */
    public function recordRequest(string $provider, string $model, string $method): void
    {
        $this->increment("requests.total");
        $this->increment("requests.by_provider.{$provider}");
        $this->increment("requests.by_model.{$model}");
        $this->increment("requests.by_method.{$method}");
    }

    /**
     * Record request latency.
     */
    public function recordLatency(string $provider, string $model, float $duration): void
    {
        $this->observe("latency.all", $duration);
        $this->observe("latency.by_provider.{$provider}", $duration);
        $this->observe("latency.by_model.{$model}", $duration);
    }

    /**
     * Record token usage.
     */
    public function recordTokens(string $provider, string $model, int $tokens): void
    {
        $this->add("tokens.total", $tokens);
        $this->add("tokens.by_provider.{$provider}", $tokens);
        $this->add("tokens.by_model.{$model}", $tokens);
    }

    /**
     * Record cost.
     */
    public function recordCost(string $provider, string $model, float $cost): void
    {
        $this->add("cost.total", $cost);
        $this->add("cost.by_provider.{$provider}", $cost);
        $this->add("cost.by_model.{$model}", $cost);
    }

    /**
     * Record an error.
     */
    public function recordError(string $provider, string $type): void
    {
        $this->increment("errors.total");
        $this->increment("errors.by_provider.{$provider}");
        $this->increment("errors.by_type.{$type}");
    }

    /**
     * Record cache hit/miss.
     */
    public function recordCacheHit(bool $hit): void
    {
        if ($hit) {
            $this->increment("cache.hits");
        } else {
            $this->increment("cache.misses");
        }
    }

    /**
     * Start a timer.
     */
    public function startTimer(string $name): void
    {
        $this->timers[$name] = microtime(true);
    }

    /**
     * Stop a timer and record the duration.
     */
    public function stopTimer(string $name): float
    {
        if (! isset($this->timers[$name])) {
            return 0.0;
        }

        $duration = (microtime(true) - $this->timers[$name]) * 1000; // milliseconds
        unset($this->timers[$name]);

        return $duration;
    }

    /**
     * Increment a counter.
     */
    public function increment(string $key, int $value = 1): void
    {
        if (! isset($this->metrics[$key])) {
            $this->metrics[$key] = ['type' => 'counter', 'value' => 0];
        }

        $this->metrics[$key]['value'] += $value;
    }

    /**
     * Add to a gauge.
     */
    public function add(string $key, float $value): void
    {
        if (! isset($this->metrics[$key])) {
            $this->metrics[$key] = ['type' => 'gauge', 'value' => 0];
        }

        $this->metrics[$key]['value'] += $value;
    }

    /**
     * Set a gauge value.
     */
    public function set(string $key, float $value): void
    {
        $this->metrics[$key] = ['type' => 'gauge', 'value' => $value];
    }

    /**
     * Observe a value (for histograms/summaries).
     */
    public function observe(string $key, float $value): void
    {
        if (! isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'type' => 'histogram',
                'values' => [],
                'count' => 0,
                'sum' => 0.0,
            ];
        }

        $this->metrics[$key]['values'][] = $value;
        $this->metrics[$key]['count']++;
        $this->metrics[$key]['sum'] += $value;
    }

    /**
     * Get all metrics.
     */
    public function getMetrics(): array
    {
        $processed = [];

        foreach ($this->metrics as $key => $data) {
            if ($data['type'] === 'histogram') {
                $processed[$key] = [
                    'type' => 'histogram',
                    'count' => $data['count'],
                    'sum' => $data['sum'],
                    'avg' => $data['count'] > 0 ? $data['sum'] / $data['count'] : 0,
                    'min' => ! empty($data['values']) ? min($data['values']) : 0,
                    'max' => ! empty($data['values']) ? max($data['values']) : 0,
                    'p50' => $this->percentile($data['values'], 50),
                    'p95' => $this->percentile($data['values'], 95),
                    'p99' => $this->percentile($data['values'], 99),
                ];
            } else {
                $processed[$key] = $data;
            }
        }

        return $processed;
    }

    /**
     * Get a specific metric.
     */
    public function getMetric(string $key): mixed
    {
        if (! isset($this->metrics[$key])) {
            return null;
        }

        $data = $this->metrics[$key];

        // Process histogram metrics to include computed statistics
        if ($data['type'] === 'histogram') {
            return [
                'type' => 'histogram',
                'count' => $data['count'],
                'sum' => $data['sum'],
                'avg' => $data['count'] > 0 ? $data['sum'] / $data['count'] : 0,
                'min' => ! empty($data['values']) ? min($data['values']) : 0,
                'max' => ! empty($data['values']) ? max($data['values']) : 0,
                'p50' => $this->percentile($data['values'], 50),
                'p95' => $this->percentile($data['values'], 95),
                'p99' => $this->percentile($data['values'], 99),
            ];
        }

        return $data;
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->metrics = [];
        $this->timers = [];
    }

    /**
     * Calculate percentile.
     */
    private function percentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return $values[$index];
    }

    /**
     * Export metrics in Prometheus format.
     */
    public function exportPrometheus(): string
    {
        $output = '';

        foreach ($this->getMetrics() as $key => $data) {
            $metricName = str_replace('.', '_', $key);

            if ($data['type'] === 'counter' || $data['type'] === 'gauge') {
                $output .= "# TYPE {$metricName} {$data['type']}\n";
                $output .= "{$metricName} {$data['value']}\n";
            } elseif ($data['type'] === 'histogram') {
                $output .= "# TYPE {$metricName} histogram\n";
                $output .= "{$metricName}_count {$data['count']}\n";
                $output .= "{$metricName}_sum {$data['sum']}\n";
                $output .= "{$metricName}_avg {$data['avg']}\n";
            }
        }

        return $output;
    }

    /**
     * Export metrics as JSON.
     */
    public function exportJson(): string
    {
        return json_encode($this->getMetrics(), JSON_PRETTY_PRINT);
    }
}
