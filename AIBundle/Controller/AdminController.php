<?php

declare(strict_types=1);

namespace KimaiPlugin\AIBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\AIBundle\Service\ConfigurationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    private ConfigurationService $configuration;

    public function __construct(ConfigurationService $configuration)
    {
        $this->configuration = $configuration;
    }

    public function indexAction(Request $request): Response
    {
        $this->denyAccessUnlessGranted('system_configuration');

        if ($request->isMethod('POST')) {
            $apiKey = $request->request->get('ai_api_key', '');
            
            // Don't save if it's the masked value
            if (!empty($apiKey) && !str_starts_with($apiKey, '****')) {
                try {
                    $this->configuration->set('ai.openai_api_key', $apiKey);
                    $this->addFlash('success', 'AI settings saved successfully!');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to save settings: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', 'No API key provided or key was masked');
            }
            
            return $this->redirectToRoute('ai_admin');
        }

        $currentApiKey = $this->configuration->get('ai.openai_api_key');
        
        return $this->render('@AI/admin/settings.html.twig', [
            'api_key' => $currentApiKey ? '****' . substr($currentApiKey, -4) : '',
            'has_key' => !empty($currentApiKey),
            'actual_key' => $currentApiKey, // For debugging
            'ai_assets' => '@AI/ai-assets.html.twig'
        ]);
    }
}