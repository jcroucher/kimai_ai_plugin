<?php

declare(strict_types=1);

namespace KimaiPlugin\AIBundle\Service;

use KimaiPlugin\AIBundle\Service\ConfigurationService;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OpenAIService
{
    private HttpClientInterface $httpClient;
    private ConfigurationService $configuration;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct(HttpClientInterface $httpClient, ConfigurationService $configuration)
    {
        $this->httpClient = $httpClient;
        $this->configuration = $configuration;
    }

    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    private function getApiKey(): ?string
    {
        return $this->configuration->get('ai.openai_api_key');
    }

    public function chat(string $message, array $context = []): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $systemPrompt = $this->buildSystemPrompt($context);
        
        $response = $this->httpClient->request('POST', $this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message]
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000
            ]
        ]);

        return $this->handleResponse($response);
    }

    public function parseTimelog(string $timelog): array
    {
        $systemPrompt = $this->getTimelogParsingPrompt();
        $currentDate = (new \DateTime())->format('Y-m-d');
        $currentYear = (new \DateTime())->format('Y');
        
        // Add current date context to the timelog
        $contextualTimelog = "Current date: $currentDate (Year: $currentYear)\n\n" . $timelog;
        
        $response = $this->httpClient->request('POST', $this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $contextualTimelog]
                ],
                'temperature' => 0.1,
                'max_tokens' => 3000
            ]
        ]);

        $result = $this->handleResponse($response);
        
        // Parse the JSON response from the AI
        try {
            return json_decode($result['content'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Failed to parse AI response as JSON: ' . $e->getMessage());
        }
    }

    private function buildSystemPrompt(array $context): string
    {
        $prompt = "You are an AI assistant integrated into Kimai, a time tracking application. ";
        $prompt .= "You help users with time tracking tasks, answer questions about their logged time, ";
        $prompt .= "and assist with various time management activities.\n\n";
        
        if (!empty($context)) {
            $prompt .= "Context about the current user:\n";
            foreach ($context as $key => $value) {
                $prompt .= "- $key: $value\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Be helpful, concise, and focused on time tracking and project management tasks.";
        
        return $prompt;
    }

    private function getTimelogParsingPrompt(): string
    {
        return <<<EOT
You are an AI assistant that parses free-form time logs into structured data for a time tracking system.

Your task is to parse the given text and extract time entries with the following information:
- date: Date in YYYY-MM-DD format
- start_time: Start time in HH:MM format (24-hour)
- end_time: End time in HH:MM format (24-hour) 
- duration: Duration in minutes (integer)
- description: Brief description of the work done
- project: Project name (if identifiable)
- client: Client name (if identifiable)
- billable: Boolean - assume true unless explicitly stated otherwise
- rate: Hourly rate in USD (default to 90 if not specified)

IMPORTANT PROJECT/DESCRIPTION PARSING RULES:
1. If a line contains "ProjectName: Description" format, extract ProjectName as the project and Description as the description
2. If a line starts with a single word followed by a colon, that word is likely the project name
3. If the description is a single word or short phrase without context, it might be a project name - check if it could be a project under the specified client
4. Look for project indicators in the broader context (e.g., "Customer is X" suggests subsequent items relate to that customer)
5. Common project patterns: single words like "Shed", "Website", "App", "Marketing" are often project names

CLIENT/CUSTOMER DETECTION:
- Look for "Customer is [Name]" or "Client: [Name]" patterns
- If specified once, apply to all subsequent entries until a new client is mentioned
- Common client indicators: "for [ClientName]", "at [ClientName]", "with [ClientName]"

RATE DETECTION:
- Look for "Hourly rate $X" or "$X/hr" patterns
- If specified once, apply to all subsequent entries
- Default to $90/hr if not specified

TIME PARSING:
1. If no date is specified, assume today's date (use the current date provided in the user message)
2. When parsing dates like "24 July" or "July 24", use the current year from the context
3. If only duration is given (no start/end times), set start_time to null and end_time to null
4. If start time is given but no end time, calculate end time using duration
5. Always mark entries as billable unless explicitly stated otherwise
6. IMPORTANT: Always use the current year (2025) unless a different year is explicitly specified

Example input formats to handle:
- "9:00 - 10:00 - Shed" → project: "Shed", description: "Work on Shed"
- "9:00 - 10:00 - Shed: Foundation work" → project: "Shed", description: "Foundation work"
- "Website development" → project: "Website", description: "Website development"

Return ONLY valid JSON array of objects, no additional text.

Example output format:
[
  {
    "date": "2025-07-24",
    "start_time": "09:00",
    "end_time": "10:30",
    "duration": 90,
    "description": "Foundation work",
    "project": "Shed",
    "client": "Wynnes",
    "billable": true,
    "rate": 90
  }
]

Parse the following time log:
EOT;
    }

    private function handleResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        
        if ($statusCode !== 200) {
            $error = json_decode($content, true);
            throw new \RuntimeException(
                'OpenAI API error: ' . ($error['error']['message'] ?? 'Unknown error'),
                $statusCode
            );
        }
        
        $data = json_decode($content, true);
        
        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? []
        ];
    }
}