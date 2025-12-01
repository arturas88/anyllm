<?php

require __DIR__ . '/../vendor/autoload.php';

use AnyLLM\Metrics\MetricsCollector;
use AnyLLM\Metrics\MetricsDashboard;

echo "=== Metrics & Monitoring Examples ===\n\n";

// =============================================
// Example 1: Basic Metrics Collection
// =============================================
echo "=== 1. Basic Metrics Collection ===\n\n";

$metrics = new MetricsCollector();

// Record some requests
for ($i = 0; $i < 10; $i++) {
    $provider = $i % 2 === 0 ? 'openai' : 'anthropic';
    $model = $i % 2 === 0 ? 'gpt-4o' : 'claude-opus-4-5';
    
    $metrics->recordRequest($provider, $model, 'chat');
    $metrics->recordLatency($provider, $model, rand(500, 2000));
    $metrics->recordTokens($provider, $model, rand(50, 200));
    $metrics->recordCost($provider, $model, rand(1, 10) / 1000);
}

// Record some errors
$metrics->recordError('openai', 'rate_limit');
$metrics->recordError('anthropic', 'auth');

// Record cache hits/misses
for ($i = 0; $i < 20; $i++) {
    $metrics->recordCacheHit($i < 15); // 75% hit rate
}

echo "✓ Recorded metrics\n\n";

// Get all metrics
$allMetrics = $metrics->getMetrics();
echo "Total requests: " . $allMetrics['requests.total']['value'] . "\n";
echo "Total tokens: " . $allMetrics['tokens.total']['value'] . "\n";
echo "Total cost: $" . number_format($allMetrics['cost.total']['value'], 4) . "\n\n";

// =============================================
// Example 2: Latency Tracking
// =============================================
echo "=== 2. Latency Tracking ===\n\n";

$metrics = new MetricsCollector();

// Simulate various latencies
$latencies = [100, 150, 200, 250, 500, 1000, 1500, 2000, 3000, 5000];
foreach ($latencies as $latency) {
    $metrics->recordLatency('openai', 'gpt-4o', $latency);
}

$latencyMetrics = $metrics->getMetric('latency.all');

echo "Latency Statistics:\n";
echo "- Count: {$latencyMetrics['count']}\n";
echo "- Average: " . round($latencyMetrics['avg'], 2) . "ms\n";
echo "- Min: {$latencyMetrics['min']}ms\n";
echo "- Max: {$latencyMetrics['max']}ms\n";
echo "- P50: " . round($latencyMetrics['p50'], 2) . "ms\n";
echo "- P95: " . round($latencyMetrics['p95'], 2) . "ms\n";
echo "- P99: " . round($latencyMetrics['p99'], 2) . "ms\n\n";

// =============================================
// Example 3: Timers
// =============================================
echo "=== 3. Operation Timers ===\n\n";

$metrics = new MetricsCollector();

// Time an operation
$metrics->startTimer('api_call');
usleep(rand(100000, 500000)); // Simulate API call
$duration = $metrics->stopTimer('api_call');

echo "API call took: " . round($duration, 2) . "ms\n\n";

// Time multiple operations
for ($i = 1; $i <= 5; $i++) {
    $metrics->startTimer("operation_{$i}");
    usleep(rand(50000, 150000));
    $duration = $metrics->stopTimer("operation_{$i}");
    echo "Operation {$i}: " . round($duration, 2) . "ms\n";
}

echo "\n";

// =============================================
// Example 4: Counter and Gauge Metrics
// =============================================
echo "=== 4. Counter and Gauge Metrics ===\n\n";

$metrics = new MetricsCollector();

// Counter (always increases)
$metrics->increment('api.calls');
$metrics->increment('api.calls');
$metrics->increment('api.calls', 5);

echo "API calls counter: " . $metrics->getMetric('api.calls')['value'] . "\n";

// Gauge (can go up or down)
$metrics->set('active_connections', 10);
$metrics->add('active_connections', 5);
$metrics->add('active_connections', -3);

echo "Active connections: " . $metrics->getMetric('active_connections')['value'] . "\n\n";

// =============================================
// Example 5: Metrics Dashboard
// =============================================
echo "=== 5. Metrics Dashboard ===\n\n";

$metrics = new MetricsCollector();

// Generate some realistic data
for ($i = 0; $i < 100; $i++) {
    $providers = ['openai', 'anthropic', 'google'];
    $provider = $providers[array_rand($providers)];
    
    $metrics->recordRequest($provider, 'test-model', 'chat');
    $metrics->recordLatency($provider, 'test-model', rand(200, 3000));
    $metrics->recordTokens($provider, 'test-model', rand(50, 500));
    $metrics->recordCost($provider, 'test-model', rand(1, 50) / 1000);
    
    // Random errors (5% rate)
    if (rand(1, 20) === 1) {
        $metrics->recordError($provider, rand(0, 1) ? 'rate_limit' : 'auth');
    }
    
    // Cache hits/misses (80% hit rate)
    $metrics->recordCacheHit(rand(1, 10) <= 8);
}

$dashboard = new MetricsDashboard($metrics);

// Text dashboard
echo $dashboard->render();

// =============================================
// Example 6: Prometheus Export
// =============================================
echo "=== 6. Prometheus Export ===\n\n";

$prometheus = $metrics->exportPrometheus();

echo "Prometheus format:\n";
echo str_repeat('-', 60) . "\n";
echo substr($prometheus, 0, 500) . "...\n";
echo str_repeat('-', 60) . "\n\n";

// =============================================
// Example 7: JSON Export
// =============================================
echo "=== 7. JSON Export ===\n\n";

$json = $metrics->exportJson();

echo "JSON format (first 500 chars):\n";
echo str_repeat('-', 60) . "\n";
echo substr($json, 0, 500) . "...\n";
echo str_repeat('-', 60) . "\n\n";

// =============================================
// Example 8: Real-Time Monitoring
// =============================================
echo "=== 8. Real-Time Monitoring ===\n\n";

$metrics = new MetricsCollector();

function simulateTraffic($metrics): void {
    static $requestCount = 0;
    
    for ($i = 0; $i < 5; $i++) {
        $requestCount++;
        
        $providers = ['openai', 'anthropic'];
        $provider = $providers[array_rand($providers)];
        
        $metrics->startTimer('request');
        usleep(rand(100000, 300000)); // Simulate request
        $duration = $metrics->stopTimer('request');
        
        $metrics->recordRequest($provider, 'test-model', 'chat');
        $metrics->recordLatency($provider, 'test-model', $duration);
        $metrics->recordTokens($provider, 'test-model', rand(50, 200));
        
        // Occasionally record errors
        if (rand(1, 10) === 1) {
            $metrics->recordError($provider, 'timeout');
        }
    }
}

echo "Simulating traffic (5 second intervals)...\n\n";

for ($iteration = 1; $iteration <= 3; $iteration++) {
    simulateTraffic($metrics);
    
    echo "Iteration {$iteration}:\n";
    $currentMetrics = $metrics->getMetrics();
    echo "- Requests: " . ($currentMetrics['requests.total']['value'] ?? 0) . "\n";
    echo "- Errors: " . ($currentMetrics['errors.total']['value'] ?? 0) . "\n";
    echo "- Tokens: " . ($currentMetrics['tokens.total']['value'] ?? 0) . "\n\n";
    
    sleep(1);
}

// =============================================
// Example 9: Cost Analytics
// =============================================
echo "=== 9. Cost Analytics ===\n\n";

$metrics = new MetricsCollector();

// Simulate usage across different providers
$providers = [
    'openai' => ['requests' => 100, 'avg_cost' => 0.005],
    'anthropic' => ['requests' => 50, 'avg_cost' => 0.008],
    'google' => ['requests' => 30, 'avg_cost' => 0.003],
];

foreach ($providers as $provider => $data) {
    for ($i = 0; $i < $data['requests']; $i++) {
        $cost = $data['avg_cost'] * (0.8 + (rand(0, 40) / 100)); // ±20% variation
        $metrics->recordCost($provider, 'model', $cost);
    }
}

$costMetrics = $metrics->getMetrics();

echo "Cost Analysis:\n";
echo "- Total Cost: $" . number_format($costMetrics['cost.total']['value'], 4) . "\n";
echo "- OpenAI: $" . number_format($costMetrics['cost.by_provider.openai']['value'], 4) . "\n";
echo "- Anthropic: $" . number_format($costMetrics['cost.by_provider.anthropic']['value'], 4) . "\n";
echo "- Google: $" . number_format($costMetrics['cost.by_provider.google']['value'], 4) . "\n\n";

// =============================================
// Example 10: HTML Dashboard
// =============================================
echo "=== 10. HTML Dashboard ===\n\n";

$html = $dashboard->renderHtml();

// Save to file
$filename = __DIR__ . '/../storage/metrics-dashboard.html';
file_put_contents($filename, $html);

echo "✓ HTML dashboard saved to: {$filename}\n";
echo "Open in browser to view interactive dashboard\n\n";

echo "=== All Metrics Examples Complete! ===\n\n";

echo "Key Features:\n";
echo "- MetricsCollector: Collect counters, gauges, histograms\n";
echo "- MetricsDashboard: Text and HTML dashboards\n";
echo "- Prometheus export: For Grafana integration\n";
echo "- JSON export: For custom integrations\n\n";

echo "Use Cases:\n";
echo "- Monitor API usage and costs\n";
echo "- Track performance (latency, throughput)\n";
echo "- Analyze error rates\n";
echo "- Cache hit rate monitoring\n";
echo "- Cost optimization\n";
echo "- SLA compliance\n";
echo "- Capacity planning\n";

