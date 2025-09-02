<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Service;

use GuzzleHttp\ClientInterface;

/**
 * Simple API client to test the toolchain.
 */
class ApiClient
{

    /**
     * Get users from API.
     *
     * @return array{data: array<mixed>, total: int}
     *   Array of users.
     */
    public function getUsers(): array
    {
        // Dummy implementation for now
        return [
            'data' => [],
            'total' => 0,
        ];
    }
}