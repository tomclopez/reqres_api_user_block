<?php

declare(strict_types=1);

namespace Drupal\Tests\reqres_api_user_block\Unit\Event;

use Drupal\reqres_api_user_block\Data\User;
use Drupal\reqres_api_user_block\Data\UserInterface;
use Drupal\reqres_api_user_block\Event\UserListEvent;
use PHPUnit\Framework\TestCase;

class UserListEventTest extends TestCase
{
    private UserInterface $user1;
    private UserInterface $user2;
    private UserInterface $user3;
    private array $context;

    protected function setUp(): void
    {
        $this->user1 = new User(
            1,
            "john@example.com",
            "John",
            "Doe",
            "avatar1.jpg",
        );
        $this->user2 = new User(
            2,
            "jane@example.com",
            "Jane",
            "Smith",
            "avatar2.jpg",
        );
        $this->user3 = new User(
            3,
            "bob@test.com",
            "Bob",
            "Johnson",
            "avatar3.jpg",
        );

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
            ],
        ];
    }

    public function testConstructor(): void
    {
        $users = [$this->user1, $this->user2];
        $event = new UserListEvent($users, $this->context);

        $this->assertSame($users, $event->getUsers());
        $this->assertSame($this->context, $event->getContext());
    }

    public function testGetUsers(): void
    {
        $users = [$this->user1, $this->user2];
        $event = new UserListEvent($users, $this->context);

        $this->assertSame($users, $event->getUsers());
        $this->assertCount(2, $event->getUsers());
    }

    public function testSetUsers(): void
    {
        $event = new UserListEvent([$this->user1], $this->context);
        $newUsers = [$this->user2, $this->user3];

        $event->setUsers($newUsers);

        $this->assertSame($newUsers, $event->getUsers());
        $this->assertCount(2, $event->getUsers());
    }

    public function testAddUser(): void
    {
        $event = new UserListEvent([$this->user1], $this->context);

        $event->addUser($this->user2);

        $users = $event->getUsers();
        $this->assertCount(2, $users);
        $this->assertSame($this->user1, $users[0]);
        $this->assertSame($this->user2, $users[1]);
    }

    public function testRemoveUserById(): void
    {
        $event = new UserListEvent(
            [$this->user1, $this->user2, $this->user3],
            $this->context,
        );

        $event->removeUserById(2);

        $users = $event->getUsers();
        $this->assertCount(2, $users);

        $userIds = array_map(fn($user) => $user->getId(), $users);
        $this->assertContains(1, $userIds);
        $this->assertContains(3, $userIds);
        $this->assertNotContains(2, $userIds);
    }

    public function testRemoveUserByIdNotExists(): void
    {
        $originalUsers = [$this->user1, $this->user2];
        $event = new UserListEvent($originalUsers, $this->context);

        $event->removeUserById(999);

        $this->assertSame($originalUsers, $event->getUsers());
        $this->assertCount(2, $event->getUsers());
    }

    public function testFilterUsers(): void
    {
        $event = new UserListEvent(
            [$this->user1, $this->user2, $this->user3],
            $this->context,
        );

        $event->filterUsers(
            fn($user) => str_ends_with($user->getEmail(), "@example.com"),
        );

        $users = $event->getUsers();
        $this->assertCount(2, $users);

        $emails = array_map(fn($user) => $user->getEmail(), $users);
        $this->assertContains("john@example.com", $emails);
        $this->assertContains("jane@example.com", $emails);
        $this->assertNotContains("bob@test.com", $emails);
    }

    public function testFilterUsersEmptyResult(): void
    {
        $event = new UserListEvent(
            [$this->user1, $this->user2],
            $this->context,
        );

        $event->filterUsers(
            fn($user) => str_ends_with($user->getEmail(), "@nonexistent.com"),
        );

        $this->assertCount(0, $event->getUsers());
        $this->assertTrue($event->isEmpty());
    }

    public function testGetContext(): void
    {
        $event = new UserListEvent([], $this->context);

        $this->assertSame($this->context, $event->getContext());
    }

    public function testGetContextValue(): void
    {
        $event = new UserListEvent([], $this->context);

        $this->assertSame(1, $event->getContextValue("page"));
        $this->assertSame(6, $event->getContextValue("per_page"));
        $this->assertSame(12, $event->getContextValue("total"));
    }

    public function testGetContextValueWithDefault(): void
    {
        $event = new UserListEvent([], $this->context);

        $this->assertSame(
            "default",
            $event->getContextValue("nonexistent", "default"),
        );
        $this->assertNull($event->getContextValue("nonexistent"));
    }

    public function testGetPage(): void
    {
        $event = new UserListEvent([], $this->context);
        $this->assertSame(1, $event->getPage());

        $contextWithPage3 = array_merge($this->context, ["page" => 3]);
        $event2 = new UserListEvent([], $contextWithPage3);
        $this->assertSame(3, $event2->getPage());
    }

    public function testGetPageDefault(): void
    {
        $contextWithoutPage = array_diff_key($this->context, ["page" => ""]);
        $event = new UserListEvent([], $contextWithoutPage);

        $this->assertSame(1, $event->getPage());
    }

    public function testGetPerPage(): void
    {
        $event = new UserListEvent([], $this->context);
        $this->assertSame(6, $event->getPerPage());

        $contextWithPerPage10 = array_merge($this->context, ["per_page" => 10]);
        $event2 = new UserListEvent([], $contextWithPerPage10);
        $this->assertSame(10, $event2->getPerPage());
    }

    public function testGetPerPageDefault(): void
    {
        $contextWithoutPerPage = array_diff_key($this->context, [
            "per_page" => "",
        ]);
        $event = new UserListEvent([], $contextWithoutPerPage);

        $this->assertSame(6, $event->getPerPage());
    }

    public function testGetTotal(): void
    {
        $event = new UserListEvent([], $this->context);
        $this->assertSame(12, $event->getTotal());

        $contextWithTotal50 = array_merge($this->context, ["total" => 50]);
        $event2 = new UserListEvent([], $contextWithTotal50);
        $this->assertSame(50, $event2->getTotal());
    }

    public function testGetTotalDefault(): void
    {
        $contextWithoutTotal = array_diff_key($this->context, ["total" => ""]);
        $event = new UserListEvent([], $contextWithoutTotal);

        $this->assertSame(0, $event->getTotal());
    }

    public function testGetBlockConfig(): void
    {
        $event = new UserListEvent([], $this->context);

        $expected = [
            "items_per_page" => 6,
            "email_label" => "Email",
            "forename_label" => "First Name",
            "surname_label" => "Last Name",
        ];

        $this->assertSame($expected, $event->getBlockConfig());
    }

    public function testGetBlockConfigDefault(): void
    {
        $contextWithoutBlockConfig = array_diff_key($this->context, [
            "block_config" => "",
        ]);
        $event = new UserListEvent([], $contextWithoutBlockConfig);

        $this->assertSame([], $event->getBlockConfig());
    }

    public function testGetUserCount(): void
    {
        $event = new UserListEvent(
            [$this->user1, $this->user2],
            $this->context,
        );
        $this->assertSame(2, $event->getUserCount());

        $event->addUser($this->user3);
        $this->assertSame(3, $event->getUserCount());

        $event->removeUserById(1);
        $this->assertSame(2, $event->getUserCount());
    }

    public function testIsEmpty(): void
    {
        $event = new UserListEvent([], $this->context);
        $this->assertTrue($event->isEmpty());

        $event->addUser($this->user1);
        $this->assertFalse($event->isEmpty());

        $event->setUsers([]);
        $this->assertTrue($event->isEmpty());
    }

    public function testEventName(): void
    {
        $this->assertSame(
            "reqres_api_user_block.users.pre_render",
            UserListEvent::NAME,
        );
    }

    public function testChainedOperations(): void
    {
        $event = new UserListEvent(
            [$this->user1, $this->user2, $this->user3],
            $this->context,
        );

        // Chain multiple operations
        $event->removeUserById(2);
        $event->addUser(
            new User(4, "alice@example.com", "Alice", "Wilson", "avatar4.jpg"),
        );
        $event->filterUsers(
            fn($user) => str_ends_with($user->getEmail(), "@example.com"),
        );

        $users = $event->getUsers();
        $this->assertCount(2, $users);

        $emails = array_map(fn($user) => $user->getEmail(), $users);
        $this->assertContains("john@example.com", $emails);
        $this->assertContains("alice@example.com", $emails);
        $this->assertNotContains("jane@example.com", $emails);
        $this->assertNotContains("bob@test.com", $emails);
    }
}
