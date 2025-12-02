<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AnyLLM\AnyLLM;
use AnyLLM\Agents\Agent;
use AnyLLM\Agents\ToolExecution;
use AnyLLM\Tools\Tool;
use AnyLLM\StructuredOutput\Attributes\Description;

echo "=== Agent Human In The Loop Example ===\n\n";

// Initialize the LLM provider
$llm = AnyLLM::openai(apiKey: $_ENV['OPENAI_API_KEY'] ?? 'your-api-key');

// Define a tool that sends emails (requires human approval)
$sendEmailTool = Tool::fromCallable(
    name: 'send_email',
    handler: function (
        #[Description('Recipient email address')]
        string $to,
        #[Description('Email subject')]
        string $subject,
        #[Description('Email body content')]
        string $body
    ): array {
        // In a real application, this would send an actual email
        // For demo purposes, we'll just return a success message
        return [
            'success' => true,
            'to' => $to,
            'subject' => $subject,
            'message' => 'Email sent successfully',
        ];
    },
    description: 'Send an email to a recipient',
);

// Define a tool that deletes files (requires human approval)
$deleteFileTool = Tool::fromCallable(
    name: 'delete_file',
    handler: function (
        #[Description('Path to the file to delete')]
        string $filePath
    ): array {
        // In a real application, this would delete the file
        return [
            'success' => true,
            'file' => $filePath,
            'message' => 'File deleted successfully',
        ];
    },
    description: 'Delete a file from the filesystem',
);

// Define a tool that makes API calls (requires human approval for external calls)
$apiCallTool = Tool::fromCallable(
    name: 'make_api_call',
    handler: function (
        #[Description('API endpoint URL')]
        string $url,
        #[Description('HTTP method (GET, POST, PUT, DELETE)')]
        string $method = 'GET',
        #[Description('Request body data (JSON string)')]
        string $body = ''
    ): array {
        // In a real application, this would make an actual HTTP request
        return [
            'success' => true,
            'url' => $url,
            'method' => $method,
            'status' => 200,
            'response' => ['message' => 'API call successful'],
        ];
    },
    description: 'Make an HTTP API call to an external service',
);

// Create an agent with Human In The Loop callbacks
$agent = Agent::create(
    provider: $llm,
    model: 'gpt-4o-mini',
    systemPrompt: 'You are a helpful assistant. When you need to perform sensitive operations like sending emails, deleting files, or making external API calls, you will request human approval first.',
)->withTools($sendEmailTool, $deleteFileTool, $apiCallTool)
  ->withBeforeToolExecution(function (string $toolName, array $arguments): bool {
      // Human approval required for sensitive operations
      $sensitiveTools = ['send_email', 'delete_file', 'make_api_call'];
      
      if (in_array($toolName, $sensitiveTools)) {
          echo "\n⚠️  HUMAN APPROVAL REQUIRED\n";
          echo "Tool: {$toolName}\n";
          echo "Arguments: " . json_encode($arguments, JSON_PRETTY_PRINT) . "\n";
          echo "\nDo you want to proceed? (yes/no): ";
          
          // In a real application, this would be a proper user input mechanism
          // For demo purposes, we'll simulate approval based on the operation
          $handle = fopen('php://stdin', 'r');
          $line = trim(fgets($handle));
          fclose($handle);
          
          $approved = strtolower($line) === 'yes' || strtolower($line) === 'y';
          
          if ($approved) {
              echo "✅ Approved. Proceeding...\n\n";
              return true;
          } else {
              echo "❌ Denied. Skipping tool execution.\n\n";
              return false;
          }
      }
      
      // Auto-approve non-sensitive tools
      return true;
  })
  ->withAfterToolExecution(function (ToolExecution $execution): mixed {
      // Optionally review/modify tool results
      if ($execution->name === 'send_email') {
          echo "📧 Email sent. Result: " . json_encode($execution->result) . "\n";
      }
      
      // Return null to use original result, or return modified result
      return null;
  })
  ->withBeforeFinalResponse(function (string $content, array $messages, array $toolExecutions): ?string {
      // Optionally review/modify the final response before returning
      echo "\n📝 Final response preview:\n";
      echo substr($content, 0, 200) . (strlen($content) > 200 ? '...' : '') . "\n";
      
      // Return null to use original content, or return modified content
      return null;
  });

echo "=== Example 1: Email Sending with Human Approval ===\n\n";
echo "Request: Send an email to john@example.com with subject 'Meeting Reminder' and body 'Don't forget about our meeting tomorrow.'\n\n";

// Note: In a real CLI environment, this would wait for user input
// For demo purposes, we'll show how it works
echo "Note: This example requires interactive input. In a real scenario:\n";
echo "1. The agent would call send_email tool\n";
echo "2. The beforeToolExecution callback would request approval\n";
echo "3. User would approve or deny\n";
echo "4. Tool executes only if approved\n\n";

// Simulate the flow
$result = $agent->run('Send an email to john@example.com with subject "Meeting Reminder" and body "Don\'t forget about our meeting tomorrow."');

echo "Agent Response:\n{$result->content}\n\n";

echo "=== Example 2: File Deletion with Human Approval ===\n\n";
echo "Request: Delete the file /tmp/old-backup.zip\n\n";

$result2 = $agent->run('Delete the file /tmp/old-backup.zip');

echo "Agent Response:\n{$result2->content}\n\n";

echo "=== Example 3: External API Call with Human Approval ===\n\n";
echo "Request: Make a POST request to https://api.example.com/users with user data\n\n";

$result3 = $agent->run('Make a POST request to https://api.example.com/users with {"name": "John", "email": "john@example.com"}');

echo "Agent Response:\n{$result3->content}\n\n";

echo "=== Summary ===\n";
echo "Human In The Loop features:\n";
echo "1. ✅ Before Tool Execution: Request approval for sensitive operations\n";
echo "2. ✅ After Tool Execution: Review and optionally modify tool results\n";
echo "3. ✅ Before Final Response: Review and optionally modify final output\n";
echo "\nThis pattern is useful for:\n";
echo "- Security-sensitive operations (file deletion, email sending)\n";
echo "- Cost-sensitive operations (expensive API calls)\n";
echo "- Quality control (reviewing AI-generated content)\n";
echo "- Compliance requirements (audit trails, approvals)\n";

echo "\nAll examples completed!\n";

