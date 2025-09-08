<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\reqres_api_user_block\Event\UserListEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Example EventSubscriber showing how to filter users.
 *
 * To use this in your own module:
 * 1. Copy this class to your module's src/EventSubscriber/ directory
 * 2. Update the namespace to match your module
 * 3. Register it as a service in your module's services.yml:
 *
 * services:
 *   my_custom_module.user_filter_subscriber:
 *     class: Drupal\my_custom_module\EventSubscriber\ExampleUserFilterSubscriber
 *     tags:
 *       - { name: event_subscriber }
 */
class ExampleUserFilterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserListEvent::NAME => [
                ['filterByEmail', 100],      // High priority
                ['filterByName', 50],        // Medium priority
                ['limitUsers', -100],        // Low priority (runs last)
            ],
        ];
    }

    /**
     * Filter out users with specific email domains.
     */
    public function filterByEmail(UserListEvent $event): void
    {
        $blockedDomains = ['@exclude.com', '@spam.net'];

        $event->filterUsers(function($user) use ($blockedDomains) {
            foreach ($blockedDomains as $domain) {
                if (str_ends_with($user->getEmail(), $domain)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Filter out users with specific names.
     */
    public function filterByName(UserListEvent $event): void
    {
        $blockedNames = ['Admin', 'Test User'];

        $event->filterUsers(function($user) use ($blockedNames) {
            $fullName = $user->getFirstName() . ' ' . $user->getLastName();
            return !in_array($fullName, $blockedNames, true);
        });
    }

    /**
     * Limit the number of users based on block configuration.
     */
    public function limitUsers(UserListEvent $event): void
    {
        $blockConfig = $event->getBlockConfig();

        // Example: If block has custom max_users setting
        if (isset($blockConfig['max_users'])) {
            $maxUsers = (int) $blockConfig['max_users'];
            $users = $event->getUsers();

            if (count($users) > $maxUsers) {
                $event->setUsers(array_slice($users, 0, $maxUsers));
            }
        }
    }

    /**
     * Example: Add custom users (uncomment to use).
     */
    /*
    public function addCustomUsers(UserListEvent $event): void
    {
        // Only add on first page
        if ($event->getPage() === 1) {
            $customUser = new User(
                9999,
                'admin@example.com',
                'Site',
                'Administrator',
                '/path/to/admin-avatar.jpg'
            );

            $event->addUser($customUser);
        }
    }
    */

    /**
     * Example: Remove specific user by ID.
     */
    /*
    public function removeSpecificUser(UserListEvent $event): void
    {
        $event->removeUserById(2); // Remove user with ID 2
    }
    */
}
