# ReqRes API User Block

A Drupal module that provides a configurable block listing users from the ReqRes API (https://reqres.in/).

## Usage

1. **Installation**: Install the module via Composer or place in your Drupal modules directory
2. **Enable Module**: Enable the "ReqRes API User Block" module in Drupal
   - **The block is automatically placed on the front page** in the content region (Olivero theme)
3. **View Block**: Visit your site's front page to see the ReqRes Users block in action
4. **Configure Block** (Optional): Go to Structure > Block Layout and configure the "ReqRes Users" block to customize:
   - Number of items per page
   - Column labels

## Extension Point

This module provides an event-based extension point that allows other modules to filter or modify the user list before display.

### Event: `reqres_api_user_block.users.pre_render`

The `UserListEvent` is dispatched before users are rendered, containing:
- Array of `User` objects (mutable)
- Context data: page, per_page, total, cache_lifetime, block_config

### Example EventSubscriber

```php
<?php

namespace Drupal\my_module\EventSubscriber;

use Drupal\reqres_api_user_block\Event\UserListEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MyUserFilterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserListEvent::NAME => 'filterUsers',
        ];
    }

    public function filterUsers(UserListEvent $event): void
    {
        // Filter out users with specific email domains
        $event->filterUsers(function($user) {
            return !str_ends_with($user->getEmail(), '@exclude.com');
        });
    }
}
```

Register the subscriber in your module's `services.yml`:

```yaml
services:
  my_module.user_filter_subscriber:
    class: Drupal\my_module\EventSubscriber\MyUserFilterSubscriber
    tags:
      - { name: event_subscriber }
```

See `examples/ExampleUserFilterSubscriber.php` for more advanced filtering examples.

## Development

### Requirements

- PHP 8.3+
- Drupal 11.0+
- Composer

### Testing

```bash
# Run PHPStan analysis
composer phpstan

# Run PHPUnit tests  
composer test

# Run all CI checks
composer ci