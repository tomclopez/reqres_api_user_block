# ReqRes API User Block

A Drupal module that provides a configurable block listing users from the ReqRes API (https://reqres.in/).

## Features

- Paginated user listing from https://reqres.in/
- Configurable labels and items per page
- Extension points for filtering users
- Resilient API handling with caching and fallbacks

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