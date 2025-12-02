<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Agents\Workflow\Workflow;
use AnyLLM\StructuredOutput\Schema;
use AnyLLM\StructuredOutput\Attributes\{Description, ArrayOf};

echo "=== Workflow Advanced Example ===\n\n";

// Initialize the LLM provider
$llm = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? 'your-api-key');

// Example 1: Content Creation Pipeline
echo "=== Example 1: Content Creation Pipeline ===\n\n";

class BlogPost
{
    #[Description('Blog post title')]
    public string $title;

    #[Description('Blog post introduction paragraph')]
    public string $introduction;

    #[Description('Main content sections')]
    #[ArrayOf('string')]
    public array $sections;

    #[Description('Conclusion paragraph')]
    public string $conclusion;

    #[Description('SEO keywords')]
    #[ArrayOf('string')]
    public array $keywords;
}

class SocialMediaPosts
{
    #[Description('Twitter/X post')]
    public string $twitter;

    #[Description('LinkedIn post')]
    public string $linkedin;

    #[Description('Facebook post')]
    public string $facebook;
}

$contentWorkflow = Workflow::create(
    provider: $llm,
    defaultModel: 'gpt-4o-mini',
)
    ->addStep(
        name: 'research',
        prompt: 'Research the topic: {{topic}}. Provide key points and important information.',
    )
    ->addStep(
        name: 'outline',
        prompt: 'Create a detailed outline for a blog post about {{topic}}. Research notes: {{research}}',
    )
    ->addStep(
        name: 'write_post',
        prompt: 'Write a complete blog post based on this outline: {{outline}}. Topic: {{topic}}',
        outputSchema: Schema::fromClass(BlogPost::class),
    )
    ->addStep(
        name: 'create_social',
        prompt: 'Create social media posts promoting this blog post. Blog post: {{write_post.title}} - {{write_post.introduction}}',
        outputSchema: Schema::fromClass(SocialMediaPosts::class),
    );

$topic = 'The Future of AI in Web Development';

echo "Topic: {$topic}\n\n";

$result = $contentWorkflow->run(['topic' => $topic]);

echo "Research:\n{$result->stepResults['research']->output}\n\n";
echo "Outline:\n{$result->stepResults['outline']->output}\n\n";

$post = $result->stepResults['write_post']->output;
echo "Blog Post:\n";
echo "Title: {$post->title}\n\n";
echo "Introduction:\n{$post->introduction}\n\n";
echo "Sections:\n";
foreach ($post->sections as $i => $section) {
    echo ($i + 1) . ". {$section}\n";
}
echo "\nConclusion:\n{$post->conclusion}\n\n";
echo "Keywords: " . implode(', ', $post->keywords) . "\n\n";

$social = $result->stepResults['create_social']->output;
echo "Social Media Posts:\n";
echo "Twitter: {$social->twitter}\n\n";
echo "LinkedIn: {$social->linkedin}\n\n";
echo "Facebook: {$social->facebook}\n\n";

// Example 2: Data Analysis Workflow
echo "=== Example 2: Data Analysis Workflow ===\n\n";

class DataInsights
{
    #[Description('Key insights from the data')]
    #[ArrayOf('string')]
    public array $insights;

    #[Description('Trends identified')]
    #[ArrayOf('string')]
    public array $trends;

    #[Description('Recommendations')]
    #[ArrayOf('string')]
    public array $recommendations;
}

$analysisWorkflow = Workflow::create(
    provider: $llm,
    defaultModel: 'gpt-4o-mini',
)
    ->addStep(
        name: 'clean_data',
        prompt: 'Clean and normalize this data: {{raw_data}}. Remove duplicates and format consistently.',
    )
    ->addStep(
        name: 'analyze',
        prompt: 'Analyze the cleaned data and identify patterns. Data: {{clean_data}}',
        outputSchema: Schema::fromClass(DataInsights::class),
    )
    ->addStep(
        name: 'visualize',
        prompt: 'Suggest visualization types for these insights: {{analyze}}. Explain why each visualization would be effective.',
    )
    ->addStep(
        name: 'report',
        prompt: 'Create an executive summary report. Insights: {{analyze.insights}}, Trends: {{analyze.trends}}, Recommendations: {{analyze.recommendations}}',
    );

$rawData = <<<'DATA'
    Sales Data:
    - Q1 2024: $100k
    - Q2 2024: $120k
    - Q3 2024: $150k
    - Q4 2024: $180k

    User Growth:
    - Jan: 1,000 users
    - Feb: 1,200 users
    - Mar: 1,500 users
    - Apr: 1,800 users
    DATA;

echo "Raw Data:\n{$rawData}\n\n";

$analysisResult = $analysisWorkflow->run(['raw_data' => $rawData]);

$insights = $analysisResult->stepResults['analyze']->output;
echo "Insights:\n";
foreach ($insights->insights as $insight) {
    echo "  - {$insight}\n";
}
echo "\nTrends:\n";
foreach ($insights->trends as $trend) {
    echo "  - {$trend}\n";
}
echo "\nRecommendations:\n";
foreach ($insights->recommendations as $rec) {
    echo "  - {$rec}\n";
}
echo "\n";

echo "Visualization Suggestions:\n{$analysisResult->stepResults['visualize']->output}\n\n";
echo "Executive Report:\n{$analysisResult->stepResults['report']->output}\n\n";

// Example 3: Multi-Model Workflow
echo "=== Example 3: Multi-Model Workflow ===\n\n";

$multiModelWorkflow = Workflow::create(
    provider: $llm,
    defaultModel: 'gpt-4o-mini',
)
    ->addStep(
        name: 'draft',
        prompt: 'Create a first draft of a technical document about: {{topic}}',
        model: 'gpt-4o-mini', // Faster model for initial draft
    )
    ->addStep(
        name: 'refine',
        prompt: 'Refine and improve this draft. Make it more professional and detailed. Draft: {{draft}}',
        model: 'gpt-4o', // More capable model for refinement
    )
    ->addStep(
        name: 'proofread',
        prompt: 'Proofread and fix any grammar or spelling errors. Text: {{refine}}',
        model: 'gpt-4o-mini', // Back to faster model for simple task
    );

$docTopic = 'RESTful API Design Best Practices';

echo "Topic: {$docTopic}\n\n";

$docResult = $multiModelWorkflow->run(['topic' => $docTopic]);

echo "Draft:\n" . substr($docResult->stepResults['draft']->output, 0, 300) . "...\n\n";
echo "Refined:\n" . substr($docResult->stepResults['refine']->output, 0, 300) . "...\n\n";
echo "Proofread:\n" . substr($docResult->stepResults['proofread']->output, 0, 300) . "...\n\n";

echo "=== Workflow Context ===\n";
echo "All variables available in final context:\n";
foreach ($docResult->context->getAllVariables() as $key => $value) {
    if (is_string($value)) {
        echo "  {$key}: " . substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '') . "\n";
    } else {
        echo "  {$key}: " . gettype($value) . "\n";
    }
}

echo "\nAll examples completed!\n";
