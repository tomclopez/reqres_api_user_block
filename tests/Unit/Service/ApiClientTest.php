<?php

declare(strict_types=1);

namespace Drupal\Tests\reqres_api_user_block\Unit\Service;

use Drupal\reqres_api_user_block\Service\ApiClient;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test the ApiClient service.
 *
 * @coversDefaultClass \Drupal\reqres_api_user_block\Service\ApiClient
 */
class ApiClientTest extends TestCase
{
    /**
     * Test getting users returns expected structure.
     *
     * @covers ::getUsers
     */
    public function testGetUsers(): void
    {
        $apiClient = new ApiClient();
        
        $result = $apiClient->getUsers();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
    }
}