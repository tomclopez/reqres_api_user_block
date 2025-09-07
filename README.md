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