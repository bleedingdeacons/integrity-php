# Integrity PHP Client (`integrity-php`)

A single-file PHP REST client for the
[Integrity](https://github.com/bleedingdeacons/integrity) WordPress plugin API —
access to Unity intergroup Groups, Meetings, Positions, Members and Intergroup
Meetings.

This repository was extracted from `integrity/client/php/` so the client can be
versioned and consumed independently of the WordPress plugin.

## Requirements

- PHP 8.1+ (uses `readonly` promoted properties and `declare(strict_types=1)`)
- ext-curl / ext-json

## Files

| File | Purpose |
| --- | --- |
| `IntegrityClient.php` | The client and its response DTOs (`IntegrityClient`, `Group`, `Meeting`, `ApiResponse`, `RateLimit`, …). |
| `client-example.php` | A runnable example exercising the main endpoints. |

## Usage

```php
<?php
require_once __DIR__ . '/IntegrityClient.php';

$client = new IntegrityClient(
    baseUrl: 'https://your-wordpress-site.com',
    apiKey: 'int_your_api_key_here',
    timeout: 30,
);

// Health check (no auth required)
$health = $client->checkHealth();
echo "Unity available: " . ($health->data->unityAvailable ? 'yes' : 'no') . "\n";

// List groups
$groups = $client->getGroups(new GroupsQuery(page: 1, perPage: 50));
foreach ($groups->data as $group) {
    echo "[{$group->id}] {$group->title}\n";
}
```

Authentication is a Bearer token sent via the `Authorization` header. See
`client-example.php` for fuller usage across the other endpoints.

## License

MIT © The Bleeding Deacons
