<?php

declare(strict_types=1);

namespace Drupal\Tests\reqres_api_user_block\Unit\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\reqres_api_user_block\Service\ReqResApiService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test the ReqResApiService.
 *
 * @coversDefaultClass \Drupal\reqres_api_user_block\Service\ReqResApiService
 */
class ReqResApiServiceTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private CacheBackendInterface&MockObject $cache;
    private ReqResApiService $apiService;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->cache = $this->createMock(CacheBackendInterface::class);
        $this->apiService = new ReqResApiService(
            $this->httpClient,
            $this->cache,
        );
    }

    /**
     * Test successful users retrieval.
     *
     * @covers ::getUsers
     */
    public function testGetUsersSuccess(): void
    {
        $responseData = [
            "page" => 1,
            "per_page" => 6,
            "total" => 12,
            "total_pages" => 2,
            "data" => [
                [
                    "id" => 1,
                    "email" => "george.bluth@reqres.in",
                    "first_name" => "George",
                    "last_name" => "Bluth",
                    "avatar" => "https://reqres.in/img/faces/1-image.jpg",
                ],
                [
                    "id" => 2,
                    "email" => "janet.weaver@reqres.in",
                    "first_name" => "Janet",
                    "last_name" => "Weaver",
                    "avatar" => "https://reqres.in/img/faces/2-image.jpg",
                ],
            ],
        ];

        $response = new Response(200, [], json_encode($responseData) ?: "");

        $this->cache
            ->expects($this->once())
            ->method("get")
            ->with("reqres_api_users:page_1:per_page_6")
            ->willReturn(false);

        $this->cache
            ->expects($this->once())
            ->method("set")
            ->with(
                "reqres_api_users:page_1:per_page_6",
                $this->anything(),
                $this->anything(),
                ["reqres_api_users"],
            );

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->with("GET", "https://reqres.in/api/users", [
                "query" => ["page" => 1, "per_page" => 6],
                "headers" => ["x-api-key" => "reqres-free-v1"],
                "timeout" => 10,
            ])
            ->willReturn($response);

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(12, $result->getTotal());
        $this->assertCount(2, $result->getUsers());
        $this->assertEquals("George", $result->getUsers()[0]->getFirstName());
    }

    /**
     * Test network error handling - should return empty response.
     *
     * @covers ::getUsers
     * @covers ::getEmptyResponse
     */
    public function testGetUsersNetworkError(): void
    {
        $request = new Request("GET", "https://reqres.in/api/users");
        $exception = new ConnectException("Connection timeout", $request);

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willThrowException($exception);

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test invalid JSON response.
     *
     * @covers ::getUsers
     * @covers ::getEmptyResponse
     */
    public function testGetUsersInvalidJson(): void
    {
        $response = new Response(200, [], "invalid json {");

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test empty response structure.
     *
     * @covers ::getEmptyResponse
     */
    public function testGetEmptyResponseStructure(): void
    {
        // Force an error to test the fallback
        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willThrowException(
                new ConnectException("Test error", new Request("GET", "test")),
            );

        $result = $this->apiService->getUsers(3, 10);

        $this->assertEquals(3, $result->getPage());
        $this->assertEquals(10, $result->getPerPage());
        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test HTTP error status codes return empty response.
     *
     * @covers ::getUsers
     * @covers ::getEmptyResponse
     */
    public function testGetUsersHttpErrorStatus(): void
    {
        $response = new Response(404, [], '{"error": "Not Found"}');

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test response with missing required fields.
     *
     * @covers ::getUsers
     * @covers ::getEmptyResponse
     */
    public function testGetUsersMissingRequiredFields(): void
    {
        $incompleteResponse = [
            "page" => 1,
            // Missing per_page, total, total_pages, data
        ];

        $response = new Response(
            200,
            [],
            json_encode($incompleteResponse) ?: "",
        );

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test response with non-array data field.
     *
     * @covers ::getUsers
     * @covers ::getEmptyResponse
     */
    public function testGetUsersNonArrayData(): void
    {
        $badResponse = [
            "page" => 1,
            "per_page" => 6,
            "total" => 1,
            "total_pages" => 1,
            "data" => "not an array", // Invalid data type
        ];

        $response = new Response(200, [], json_encode($badResponse) ?: "");

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test edge case pagination values.
     *
     * @covers ::getUsers
     */
    public function testGetUsersEdgeCasePagination(): void
    {
        $responseData = [
            "page" => 0,
            "per_page" => 100,
            "total" => 0,
            "total_pages" => 0,
            "data" => [],
        ];

        $response = new Response(200, [], json_encode($responseData) ?: "");

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->with("GET", "https://reqres.in/api/users", [
                "query" => ["page" => 0, "per_page" => 100],
                "headers" => ["x-api-key" => "reqres-free-v1"],
                "timeout" => 10,
            ])
            ->willReturn($response);

        $result = $this->apiService->getUsers(0, 100);

        $this->assertEquals(0, $result->getPage());
        $this->assertEquals(100, $result->getPerPage());
    }

    /**
     * Test request parameters are correctly passed.
     *
     * @covers ::getUsers
     */
    public function testGetUsersRequestParameters(): void
    {
        $response = new Response(
            200,
            [],
            json_encode([
                "page" => 1,
                "per_page" => 6,
                "total" => 0,
                "total_pages" => 0,
                "data" => [],
            ]) ?:
            "",
        );

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->with(
                $this->equalTo("GET"),
                $this->equalTo("https://reqres.in/api/users"),
                $this->callback(function ($options) {
                    return isset(
                        $options["query"]["page"],
                        $options["query"]["per_page"],
                        $options["headers"]["x-api-key"],
                        $options["timeout"],
                    ) &&
                        $options["query"]["page"] === 1 &&
                        $options["query"]["per_page"] === 6 &&
                        $options["headers"]["x-api-key"] === "reqres-free-v1" &&
                        $options["timeout"] === 10;
                }),
            )
            ->willReturn($response);

        $this->apiService->getUsers();
    }
}
