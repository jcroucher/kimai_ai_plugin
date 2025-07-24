<?php

declare(strict_types=1);

namespace KimaiPlugin\AIBundle\Service;

use Doctrine\DBAL\Connection;

class ConfigurationService
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function get(string $key): ?string
    {
        $sql = 'SELECT value FROM kimai2_configuration WHERE name = :name';
        $result = $this->connection->fetchOne($sql, ['name' => $key]);
        
        return $result !== false ? $result : null;
    }

    public function set(string $key, string $value): void
    {
        $sql = 'INSERT INTO kimai2_configuration (name, value) VALUES (:name, :value) 
                ON DUPLICATE KEY UPDATE value = :value';
        
        $this->connection->executeStatement($sql, [
            'name' => $key,
            'value' => $value
        ]);
    }
}