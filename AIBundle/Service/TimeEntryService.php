<?php

declare(strict_types=1);

namespace KimaiPlugin\AIBundle\Service;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class TimeEntryService
{
    private EntityManagerInterface $entityManager;
    private CustomerRepository $customerRepository;
    private ProjectRepository $projectRepository;
    private ActivityRepository $activityRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        CustomerRepository $customerRepository,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository
    ) {
        $this->entityManager = $entityManager;
        $this->customerRepository = $customerRepository;
        $this->projectRepository = $projectRepository;
        $this->activityRepository = $activityRepository;
    }

    public function createTimesheetEntries(array $entries, User $user): array
    {
        $createdEntries = [];
        
        foreach ($entries as $entry) {
            $timesheet = $this->createTimesheetFromEntry($entry, $user);
            $createdEntries[] = $timesheet;
        }
        
        return $createdEntries;
    }

    public function previewEntries(array $entries, User $user): array
    {
        $previews = [];
        
        foreach ($entries as $entry) {
            $customer = $this->findOrCreateCustomer($entry['client'] ?? 'Unknown Client');
            $project = $this->findOrCreateProject($entry['project'] ?? 'General', $customer);
            $activity = $this->findDefaultActivity();
            
            $previews[] = [
                'date' => $entry['date'],
                'start_time' => $entry['start_time'],
                'end_time' => $entry['end_time'],
                'duration' => $entry['duration'],
                'description' => $entry['description'],
                'customer' => $customer->getName(),
                'project' => $project->getName(),
                'activity' => $activity->getName(),
                'billable' => $entry['billable'],
                'rate' => $entry['rate'],
                'total' => round(($entry['duration'] / 60) * $entry['rate'], 2)
            ];
        }
        
        return $previews;
    }

    private function createTimesheetFromEntry(array $entry, User $user): Timesheet
    {
        $customer = $this->findOrCreateCustomer($entry['client'] ?? 'Unknown Client');
        
        // Smart project detection - check if description might be a project name
        $projectDetection = $this->detectProjectName($entry, $customer);
        $projectName = $projectDetection['project'];
        $actualDescription = $projectDetection['description'];
        
        $project = $this->findOrCreateProject($projectName, $customer);
        $activity = $this->findDefaultActivity();
        
        $timesheet = new Timesheet();
        $timesheet->setUser($user);
        $timesheet->setProject($project);
        $timesheet->setActivity($activity);
        $timesheet->setDescription($actualDescription);
            
        // Set date and times
        $date = new \DateTime($entry['date']);
        
        if (!empty($entry['start_time']) && !empty($entry['end_time'])) {
            $begin = \DateTime::createFromFormat('Y-m-d H:i', $entry['date'] . ' ' . $entry['start_time']);
            $end = \DateTime::createFromFormat('Y-m-d H:i', $entry['date'] . ' ' . $entry['end_time']);
            
            if (!$begin || !$end) {
                throw new \RuntimeException('Invalid date/time format');
            }
            
            $timesheet->setBegin($begin);
            $timesheet->setEnd($end);
        } else {
            // If only duration is provided, set begin time and calculate end
            $begin = clone $date;
            $begin->setTime(9, 0); // Default start time
            
            $end = clone $begin;
            $end->modify('+' . ($entry['duration'] ?? 60) . ' minutes');
            
            $timesheet->setBegin($begin);
            $timesheet->setEnd($end);
        }
        
        // Set rate and billable status
        if (!empty($entry['billable']) && !empty($entry['rate'])) {
            $timesheet->setHourlyRate((float)$entry['rate']);
        }
        
        return $timesheet;
    }

    private function detectProjectName(array $entry, Customer $customer): array
    {
        $projectName = $entry['project'] ?? null;
        $description = $entry['description'] ?? '';
        
        // If project is explicitly set, use it
        if (!empty($projectName) && $projectName !== 'null') {
            return [
                'project' => $projectName,
                'description' => $description ?: "Work on $projectName"
            ];
        }
        
        // Check if description might be a project name
        if (!empty($description)) {
            // Handle "ProjectName: Task description" format
            if (strpos($description, ':') !== false) {
                $parts = explode(':', $description, 2);
                $potentialProject = trim($parts[0]);
                $taskDescription = trim($parts[1] ?? '');
                
                // Check if this project exists for the customer
                $existingProject = $this->projectRepository->findOneBy([
                    'name' => $potentialProject,
                    'customer' => $customer
                ]);
                
                if ($existingProject) {
                    return [
                        'project' => $potentialProject,
                        'description' => $taskDescription ?: "Work on $potentialProject"
                    ];
                }
            }
            
            // Check if the entire description might be a project name
            $trimmedDesc = trim($description);
            if (strlen($trimmedDesc) <= 50 && !str_contains($trimmedDesc, ' ')) {
                // Single word descriptions might be project names
                $existingProject = $this->projectRepository->findOneBy([
                    'name' => $trimmedDesc,
                    'customer' => $customer
                ]);
                
                if ($existingProject) {
                    return [
                        'project' => $trimmedDesc,
                        'description' => "Work on $trimmedDesc"
                    ];
                }
            }
            
            // Check for common project patterns in description
            $words = explode(' ', $trimmedDesc);
            if (count($words) <= 3) {
                // Short descriptions might be project names
                $existingProject = $this->projectRepository->findOneBy([
                    'name' => $trimmedDesc,
                    'customer' => $customer
                ]);
                
                if ($existingProject) {
                    return [
                        'project' => $trimmedDesc,
                        'description' => "Work on $trimmedDesc"
                    ];
                }
            }
        }
        
        // Default to "General" if no project detected
        return [
            'project' => 'General',
            'description' => $description ?: 'General work'
        ];
    }

    private function findOrCreateCustomer(string $name): Customer
    {
        $customer = $this->customerRepository->findOneBy(['name' => $name]);
        
        if (!$customer) {
            $customer = new Customer($name);
            $customer->setCountry('US');
            $customer->setCurrency('USD');
            $customer->setVisible(true);
            
            $this->entityManager->persist($customer);
            $this->entityManager->flush(); // Flush immediately to get ID
        }
        
        return $customer;
    }

    private function findOrCreateProject(string $name, Customer $customer): Project
    {
        $project = $this->projectRepository->findOneBy([
            'name' => $name,
            'customer' => $customer
        ]);
        
        if (!$project) {
            $project = new Project();
            $project->setName($name);
            $project->setCustomer($customer);
            $project->setVisible(true);
            
            $this->entityManager->persist($project);
            $this->entityManager->flush(); // Flush immediately to get ID
        }
        
        return $project;
    }

    private function findDefaultActivity(): Activity
    {
        $activity = $this->activityRepository->findOneBy(['name' => 'Work']);
        
        if (!$activity) {
            $activity = new Activity();
            $activity->setName('Work');
            $activity->setVisible(true);
            
            $this->entityManager->persist($activity);
            $this->entityManager->flush(); // Flush immediately to get ID
        }
        
        return $activity;
    }

    public function saveEntries(array $timesheets): void
    {
        try {
            // Start transaction
            $this->entityManager->beginTransaction();
            
            foreach ($timesheets as $timesheet) {
                $this->entityManager->persist($timesheet);
            }
            
            $this->entityManager->flush();
            
            // Commit transaction
            $this->entityManager->commit();
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}