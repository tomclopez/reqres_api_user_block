<?php

declare(strict_types=1);

namespace Drupal\Tests\reqres_api_user_block\Unit\EventSubscriber;

use Drupal\reqres_api_user_block\Data\User;
use Drupal\reqres_api_user_block\Event\UserListEvent;
use PHPUnit\Framework\TestCase;

/**
 * Test for the ExampleUserFilterSubscriber functionality.
 *
 * This tests the example EventSubscriber to demonstrate how extension
 * points can be tested.
 */
class ExampleUserFilterSubscriberTest extends TestCase
{
    private ExampleUserFilterSubscriberTestClass $subscriber;
    private array $users;
    private array $context;

    protected function setUp(): void
    {
        $this->subscriber = new ExampleUserFilterSubscriberTestClass();

        $this->users = [
            new User(1, "user@example.com", "John", "Doe", "avatar1.jpg"),
            new User(2, "admin@exclude.com", "Admin", "User", "avatar2.jpg"),
            new User(3, "test@spam.net", "Test", "User", "avatar3.jpg"),
            new User(4, "jane@example.com", "Jane", "Smith", "avatar4.jpg"),
            new User(
                5,
                "admin@example.com",
                "Site",
                "Administrator",
                "avatar5.jpg",
            ),
        ];

        $this->context = [
            "page" => 1,
            "per_page" => 6,
            "total" => 12,
            "total_pages" => 2,
            "cache_lifetime" => 300,
            "block_config" => [
                "items_per_page" => 6,
                "email_label" => "Email",
                "forename_label" => "First Name",
                "surname_label" => "Last Name",
                "max_users" => 3,
            ],
        ];
    }

    public function testGetSubscribedEvents(): void
    {
        $events = $this->subscriber::getSubscribedEvents();

        $this->assertArrayHasKey(UserListEvent::NAME, $events);

        $subscribers = $events[UserListEvent::NAME];
        $this->assertIsArray($subscribers);
        $this->assertCount(3, $subscribers);

        // Check priorities and method names
        $this->assertEquals(["filterByEmail", 100], $subscribers[0]);
        $this->assertEquals(["filterByName", 50], $subscribers[1]);
        $this->assertEquals(["limitUsers", -100], $subscribers[2]);
    }

    public function testFilterByEmail(): void
    {
        $event = new UserListEvent($this->users, $this->context);

        $this->subscriber->filterByEmail($event);

        $filteredUsers = $event->getUsers();
        $this->assertCount(3, $filteredUsers);

        $emails = array_map(fn($user) => $user->getEmail(), $filteredUsers);
        $this->assertContains("user@example.com", $emails);
        $this->assertContains("jane@example.com", $emails);
        $this->assertContains("admin@example.com", $emails);
        $this->assertNotContains("admin@exclude.com", $emails);
        $this->assertNotContains("test@spam.net", $emails);
    }

    public function testFilterByEmailNoMatches(): void
    {
        $users = [
            new User(1, "good@example.com", "Good", "User", "avatar1.jpg"),
            new User(
                2,
                "another@example.com",
                "Another",
                "User",
                "avatar2.jpg",
            ),
        ];

        $event = new UserListEvent($users, $this->context);

        $this->subscriber->filterByEmail($event);

        $this->assertCount(2, $event->getUsers());
    }

    public function testFilterByEmailAllFiltered(): void
    {
        $users = [
            new User(1, "bad@exclude.com", "Bad", "User", "avatar1.jpg"),
            new User(2, "spam@spam.net", "Spam", "User", "avatar2.jpg"),
        ];

        $event = new UserListEvent($users, $this->context);

        $this->subscriber->filterByEmail($event);

        $this->assertCount(0, $event->getUsers());
        $this->assertTrue($event->isEmpty());
    }

    public function testFilterByName(): void
    {
        $users = [
            new User(1, "john@example.com", "John", "Doe", "avatar1.jpg"),
            new User(2, "admin@example.com", "Admin", "User", "avatar2.jpg"),
            new User(3, "test@example.com", "Test", "User", "avatar3.jpg"),
            new User(4, "jane@example.com", "Jane", "Smith", "avatar4.jpg"),
        ];

        $event = new UserListEvent($users, $this->context);

        $this->subscriber->filterByName($event);

        $filteredUsers = $event->getUsers();
        $this->assertCount(2, $filteredUsers);

        $names = array_map(
            fn($user) => $user->getFirstName() . " " . $user->getLastName(),
            $filteredUsers,
        );
        $this->assertContains("John Doe", $names);
        $this->assertContains("Jane Smith", $names);
        $this->assertNotContains("Admin User", $names);
        $this->assertNotContains("Test User", $names);
    }

    public function testFilterByNameNoMatches(): void
    {
        $users = [
            new User(1, "john@example.com", "John", "Doe", "avatar1.jpg"),
            new User(2, "jane@example.com", "Jane", "Smith", "avatar2.jpg"),
        ];

        $event = new UserListEvent($users, $this->context);

        $this->subscriber->filterByName($event);

        $this->assertCount(2, $event->getUsers());
    }

    public function testLimitUsers(): void
    {
        $event = new UserListEvent($this->users, $this->context);

        $this->subscriber->limitUsers($event);

        $this->assertCount(3, $event->getUsers());
    }

    public function testLimitUsersNoLimit(): void
    {
        $contextWithoutLimit = $this->context;
        unset($contextWithoutLimit["block_config"]["max_users"]);

        $event = new UserListEvent($this->users, $contextWithoutLimit);

        $this->subscriber->limitUsers($event);

        $this->assertCount(5, $event->getUsers());
    }

    public function testLimitUsersHigherThanCount(): void
    {
        $contextWithHighLimit = $this->context;
        $contextWithHighLimit["block_config"]["max_users"] = 10;

        $event = new UserListEvent($this->users, $contextWithHighLimit);

        $this->subscriber->limitUsers($event);

        $this->assertCount(5, $event->getUsers());
    }

    public function testLimitUsersZero(): void
    {
        $contextWithZeroLimit = $this->context;
        $contextWithZeroLimit["block_config"]["max_users"] = 0;

        $event = new UserListEvent($this->users, $contextWithZeroLimit);

        $this->subscriber->limitUsers($event);

        $this->assertCount(0, $event->getUsers());
        $this->assertTrue($event->isEmpty());
    }

    public function testChainedFiltering(): void
    {
        $event = new UserListEvent($this->users, $this->context);

        // Apply filters in the same order as priorities
        $this->subscriber->filterByEmail($event);
        $this->subscriber->filterByName($event);
        $this->subscriber->limitUsers($event);

        $filteredUsers = $event->getUsers();
        $this->assertLessThanOrEqual(3, count($filteredUsers));

        // Verify no blocked domains or names remain
        foreach ($filteredUsers as $user) {
            $this->assertStringEndsNotWith("@exclude.com", $user->getEmail());
            $this->assertStringEndsNotWith("@spam.net", $user->getEmail());

            $fullName = $user->getFirstName() . " " . $user->getLastName();
            $this->assertNotContains($fullName, ["Admin", "Test User"]);
        }
    }

    public function testEventContextAccess(): void
    {
        $event = new UserListEvent($this->users, $this->context);

        // Test that the subscriber can access context correctly
        $this->assertEquals(1, $event->getPage());
        $this->assertEquals(6, $event->getPerPage());
        $this->assertEquals(12, $event->getTotal());
        $this->assertEquals(
            ["max_users" => 3],
            array_intersect_key($event->getBlockConfig(), ["max_users" => ""]),
        );
    }
}

/**
 * Test implementation of the ExampleUserFilterSubscriber.
 *
 * This is a copy of the example subscriber for testing purposes.
 */
class ExampleUserFilterSubscriberTestClass
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserListEvent::NAME => [
                ["filterByEmail", 100],
                ["filterByName", 50],
                ["limitUsers", -100],
            ],
        ];
    }

    public function filterByEmail(UserListEvent $event): void
    {
        $blockedDomains = ["@exclude.com", "@spam.net"];

        $event->filterUsers(function ($user) use ($blockedDomains) {
            foreach ($blockedDomains as $domain) {
                if (str_ends_with($user->getEmail(), $domain)) {
                    return false;
                }
            }
            return true;
        });
    }

    public function filterByName(UserListEvent $event): void
    {
        $blockedNames = ["Admin User", "Test User"];

        $event->filterUsers(function ($user) use ($blockedNames) {
            $fullName = $user->getFirstName() . " " . $user->getLastName();
            return !in_array($fullName, $blockedNames, true);
        });
    }

    public function limitUsers(UserListEvent $event): void
    {
        $blockConfig = $event->getBlockConfig();

        if (isset($blockConfig["max_users"])) {
            $maxUsers = (int) $blockConfig["max_users"];
            $users = $event->getUsers();

            if (count($users) > $maxUsers) {
                $event->setUsers(array_slice($users, 0, $maxUsers));
            }
        }
    }
}
