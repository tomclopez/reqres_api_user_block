<?php

declare(strict_types=1);

namespace Drupal\reqres_api_user_block\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for fetching users from ReqRes API.
 */
class ReqResApiService
{
    private const string BASE_URL = "https://reqres.in/api";

    private const string USERS_ENDPOINT = "/users";

    private ClientInterface $httpClient;

    public function __construct(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @param int $page
     * @param int $perPage
     *
     * @return array{page: int, per_page: int, total: int, total_pages: int, data: array<array{id: int, email: string, first_name: string, last_name: string, avatar: string}>}
     */
    public function getUsers(int $page = 1, int $perPage = 6): array
    {
        try {
            $response = $this->httpClient->request(
                "GET",
                self::BASE_URL . self::USERS_ENDPOINT,
                [
                    "query" => [
                        "page" => $page,
                        "per_page" => $perPage,
                    ],
                    "headers" => [
                        "x-api-key" => "reqres-free-v1",
                    ],
                    "timeout" => 10,
                ],
            );

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                return $this->getEmptyResponse($page, $perPage);
            }

            if (
                !isset(
                    $data["page"],
                    $data["per_page"],
                    $data["total"],
                    $data["total_pages"],
                    $data["data"],
                ) ||
                !is_array($data["data"])
            ) {
                return $this->getEmptyResponse($page, $perPage);
            }

            /** @var array{page: int, per_page: int, total: int, total_pages: int, data: array<array{id: int, email: string, first_name: string, last_name: string, avatar: string}>} $data */
            return $data;
        } catch (GuzzleException $e) {
            return $this->getEmptyResponse($page, $perPage);
        }
    }

    /**
     * @param int $page
     *   The page number.
     * @param int $perPage
     *   Items per page.
     *
     * @return array{page: int, per_page: int, total: int, total_pages: int, data: array<never>}
     */
    private function getEmptyResponse(int $page, int $perPage): array
    {
        return [
            "page" => $page,
            "per_page" => $perPage,
            "total" => 0,
            "total_pages" => 0,
            "data" => [],
        ];
    }
}
