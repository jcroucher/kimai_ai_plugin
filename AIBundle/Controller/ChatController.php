<?php

declare(strict_types=1);

namespace KimaiPlugin\AIBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\AIBundle\Service\OpenAIService;
use KimaiPlugin\AIBundle\Service\TimeEntryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends AbstractController
{
    private OpenAIService $openAIService;
    private TimeEntryService $timeEntryService;

    public function __construct(OpenAIService $openAIService, TimeEntryService $timeEntryService)
    {
        $this->openAIService = $openAIService;
        $this->timeEntryService = $timeEntryService;
    }

    public function chatAction(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        if (!$this->openAIService->isConfigured()) {
            return new JsonResponse([
                'error' => 'AI service not configured. Please contact your administrator.'
            ], 400);
        }

        $message = $request->request->get('message', '');
        
        if (empty($message)) {
            return new JsonResponse(['error' => 'Message is required'], 400);
        }

        try {
            $context = [
                'user' => $this->getUser()->getDisplayName(),
                'current_date' => (new \DateTime())->format('Y-m-d'),
            ];
            
            $response = $this->openAIService->chat($message, $context);
            
            return new JsonResponse([
                'response' => $response['content'],
                'usage' => $response['usage'] ?? []
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to get AI response: ' . $e->getMessage()
            ], 500);
        }
    }

    public function parseTimelogAction(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        if (!$this->openAIService->isConfigured()) {
            return new JsonResponse([
                'error' => 'AI service not configured. Please contact your administrator.'
            ], 400);
        }

        $timelog = $request->request->get('timelog', '');
        
        if (empty($timelog)) {
            return new JsonResponse(['error' => 'Time log is required'], 400);
        }

        try {
            $entries = $this->openAIService->parseTimelog($timelog);
            $preview = $this->timeEntryService->previewEntries($entries, $this->getUser());
            
            return new JsonResponse([
                'entries' => $entries,
                'preview' => $preview
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to parse time log: ' . $e->getMessage()
            ], 500);
        }
    }

    public function previewEntriesAction(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $entries = $request->request->get('entries', []);
        
        if (empty($entries)) {
            return new JsonResponse(['error' => 'Entries are required'], 400);
        }

        try {
            if (is_string($entries)) {
                $entries = json_decode($entries, true);
            }
            
            $preview = $this->timeEntryService->previewEntries($entries, $this->getUser());
            
            return new JsonResponse(['preview' => $preview]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to preview entries: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submitEntriesAction(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        // Get raw entries data - could be JSON string or array
        $entriesRaw = $request->request->get('entries');
        
        if (empty($entriesRaw)) {
            return new JsonResponse(['error' => 'Entries are required'], 400);
        }

        try {
            // Handle different input formats
            if (is_array($entriesRaw)) {
                $entries = $entriesRaw;
            } elseif (is_string($entriesRaw)) {
                $entries = json_decode($entriesRaw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid JSON in entries: ' . json_last_error_msg());
                }
            } else {
                throw new \RuntimeException('Entries must be array or JSON string');
            }
            
            if (empty($entries) || !is_array($entries)) {
                return new JsonResponse(['error' => 'No valid entries found'], 400);
            }
            
            // Create and save entries in a single transaction
            $timesheets = $this->timeEntryService->createTimesheetEntries($entries, $this->getUser());
            $this->timeEntryService->saveEntries($timesheets);
            
            return new JsonResponse([
                'success' => true,
                'message' => sprintf('Successfully created %d time entries', count($timesheets)),
                'entries_created' => count($timesheets)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to create entries: ' . $e->getMessage()
            ], 500);
        }
    }
}