<?php

declare(strict_types=1);

namespace Drupal\Tests\reqres_api_user_block\Unit\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\reqres_api_user_block\Event\UserListEvent;
use Drupal\reqres_api_user_block\Service\ReqResApiService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Test the ReqResApiService.
 *
 * @coversDefaultClass \Drupal\reqres_api_user_block\Service\ReqResApiService
 */
class ReqResApiServiceTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private CacheBackendInterface&MockObject $cache;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ReqResApiService $apiService;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->cache = $this->createMock(CacheBackendInterface::class);
        $this->eventDispatcher = $this->createMock(
            EventDispatcherInterface::class,
        );
        $this->apiService = new ReqResApiService(
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher,
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

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->with("GET", "https://reqres.in/api/users", [
                "query" => ["page" => 1, "per_page" => 6],
                "headers" => ["x-api-key" => "reqres-free-v1"],
                "timeout" => 10,
            ])
            ->willReturn($response);

        $this->cache->expects($this->once())->method("get")->willReturn(false);

        $this->eventDispatcher
            ->expects($this->once())
            ->method("dispatch")
            ->with(
                $this->isInstanceOf(UserListEvent::class),
                UserListEvent::NAME,
            )
            ->willReturnArgument(0);

        $this->cache
            ->expects($this->once())
            ->method("set")
            ->with(
                "reqres_api_users:page_1:per_page_6",
                $this->anything(),
                $this->anything(),
                ["reqres_api_users"],
            );

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(12, $result->getTotal());
        $this->assertEquals(2, $result->getTotalPages());
        $this->assertCount(2, $result->getUsers());

        $users = $result->getUsers();
        $this->assertEquals(1, $users[0]->getId());
        $this->assertEquals("george.bluth@reqres.in", $users[0]->getEmail());
        $this->assertEquals("George", $users[0]->getFirstName());
        $this->assertEquals("Bluth", $users[0]->getLastName());

        $this->assertEquals(2, $users[1]->getId());
        $this->assertEquals("janet.weaver@reqres.in", $users[1]->getEmail());
        $this->assertEquals("Janet", $users[1]->getFirstName());
        $this->assertEquals("Weaver", $users[1]->getLastName());
    }

    /**
     * Test cached response is returned.
     *
     * @covers ::getUsers
     */
    public function testGetUsersCached(): void
    {
        $cachedResult = $this->createMock(
            \Drupal\reqres_api_user_block\Service\UserProviderResultInterface::class,
        );

        $cacheObject = new \stdClass();
        $cacheObject->data = $cachedResult;
        $cacheObject->valid = true;

        $this->cache
            ->expects($this->once())
            ->method("get")
            ->with("reqres_api_users:page_1:per_page_6")
            ->willReturn($cacheObject);

        $this->httpClient->expects($this->never())->method("request");
        $this->eventDispatcher->expects($this->never())->method("dispatch");

        $result = $this->apiService->getUsers();

        $this->assertSame($cachedResult, $result);
    }

    /**
     * Test handling of HTTP exceptions.
     *
     * @covers ::getUsers
     */
    public function testGetUsersHttpException(): void
    {
        $request = new Request("GET", "https://reqres.in/api/users");
        $exception = new ConnectException("Connection failed", $request);

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willThrowException($exception);

        $this->cache->expects($this->once())->method("get")->willReturn(false);
        $this->eventDispatcher->expects($this->never())->method("dispatch");

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test handling of invalid JSON response.
     *
     * @covers ::getUsers
     */
    public function testGetUsersInvalidJson(): void
    {
        $response = new Response(200, [], "invalid json");

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $this->cache->expects($this->once())->method("get")->willReturn(false);
        $this->eventDispatcher->expects($this->never())->method("dispatch");

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test handling of malformed API response.
     *
     * @covers ::getUsers
     */
    public function testGetUsersMalformedResponse(): void
    {
        $badResponse = [
            "page" => 1,
            "per_page" => 6,
            "total" => 1,
            "total_pages" => 1,
            "data" => "not an array",
        ];

        $response = new Response(200, [], json_encode($badResponse) ?: "");

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $this->cache->expects($this->once())->method("get")->willReturn(false);
        $this->eventDispatcher->expects($this->never())->method("dispatch");

        $result = $this->apiService->getUsers();

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test that event is dispatched with correct context.
     *
     * @covers ::getUsers
     */
    public function testEventDispatchedWithCorrectContext(): void
    {
        $responseData = [
            "page" => 2,
            "per_page" => 4,
            "total" => 12,
            "total_pages" => 3,
            "data" => [
                [
                    "id" => 1,
                    "email" => "test@example.com",
                    "first_name" => "Test",
                    "last_name" => "User",
                    "avatar" => "avatar.jpg",
                ],
            ],
        ];

        $response = new Response(200, [], json_encode($responseData));
        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $this->cache->expects($this->once())->method("get")->willReturn(false);

        $blockConfig = ["test" => "config"];

        $this->eventDispatcher
            ->expects($this->once())
            ->method("dispatch")
            ->with(
                $this->callback(function (UserListEvent $event) {
                    $context = $event->getContext();
                    return $context["page"] === 2 &&
                        $context["per_page"] === 4 &&
                        $context["total"] === 12 &&
                        $context["total_pages"] === 3 &&
                        $context["cache_lifetime"] === 600 &&
                        $context["block_config"] === ["test" => "config"] &&
                        $event->getUserCount() === 1 &&
                        $event->getUsers()[0]->getEmail() ===
                            "test@example.com";
                }),
                UserListEvent::NAME,
            )
            ->willReturnArgument(0);

        $this->apiService->getUsers(2, 4, 600, ["test" => "config"]);
    }

    /**
     * Test that filtered users from event are used in result.
     *
     * @covers ::getUsers
     */
    public function testFilteredUsersFromEventUsedInResult(): void
    {
        $responseData = [
            "page" => 1,
            "per_page" => 6,
            "total" => 12,
            "total_pages" => 2,
            "data" => [
                [
                    "id" => 1,
                    "email" => "keep@example.com",
                    "first_name" => "Keep",
                    "last_name" => "Me",
                    "avatar" => "avatar1.jpg",
                ],
                [
                    "id" => 2,
                    "email" => "remove@example.com",
                    "first_name" => "Remove",
                    "last_name" => "Me",
                    "avatar" => "avatar2.jpg",
                ],
            ],
        ];

        $response = new Response(200, [], json_encode($responseData));
        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $this->cache->expects($this->once())->method("get")->willReturn(false);

        $this->eventDispatcher
            ->expects($this->once())
            ->method("dispatch")
            ->with(
                $this->isInstanceOf(UserListEvent::class),
                UserListEvent::NAME,
            )
            ->willReturnCallback(function (UserListEvent $event) {
                $event->filterUsers(fn($user) => $user->getId() !== 2);
                return $event;
            });

        $result = $this->apiService->getUsers();

        $this->assertCount(1, $result->getUsers());
        $this->assertSame(
            "keep@example.com",
            $result->getUsers()[0]->getEmail(),
        );
    }

    /**
     * Test that empty event results work correctly.
     *
     * @covers ::getUsers
     */
    public function testEmptyEventResultsHandledCorrectly(): void
    {
        $responseData = [
            "page" => 1,
            "per_page" => 6,
            "total" => 12,
            "total_pages" => 2,
            "data" => [
                [
                    "id" => 1,
                    "email" => "test@example.com",
                    "first_name" => "Test",
                    "last_name" => "User",
                    "avatar" => "avatar.jpg",
                ],
            ],
        ];

        $response = new Response(200, [], json_encode($responseData));
        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $this->cache->expects($this->once())->method("get")->willReturn(false);

        $this->eventDispatcher
            ->expects($this->once())
            ->method("dispatch")
            ->willReturnCallback(function (UserListEvent $event) {
                $event->setUsers([]);
                return $event;
            });

        $result = $this->apiService->getUsers();

        $this->assertCount(0, $result->getUsers());
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

        $this->cache->expects($this->once())->method("get")->willReturn(false);

        $this->eventDispatcher
            ->expects($this->once())
            ->method("dispatch")
            ->willReturnArgument(0);

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

        $this->cache->expects($this->once())->method("get")->willReturn(false);

        $this->eventDispatcher
            ->expects($this->once())
            ->method("dispatch")
            ->willReturnArgument(0);

        $this->apiService->getUsers();
    }

    /**
     * Test caching is skipped when cache lifetime is 0.
     *
     * @covers ::getUsers
     */
    public function testGetUsersNoCaching(): void
    {
        $responseData = [
            "page" => 1,
            "per_page" => 6,
            "total" => 0,
            "total_pages" => 0,
            "data" => [],
        ];

        $response = new Response(200, [], json_encode($responseData) ?: "");

        $this->httpClient
            ->expects($this->once())
            ->method("request")
            ->willReturn($response);

        $this->cache->expects($this->never())->method("get");
        $this->cache->expects($this->never())->method("set");

        $this->eventDispatcher
            ->expects($this->once())
            ->method("dispatch")
            ->willReturnArgument(0);

        $result = $this->apiService->getUsers(1, 6, 0);

        $this->assertEquals(1, $result->getPage());
        $this->assertEquals(6, $result->getPerPage());
    }
}
