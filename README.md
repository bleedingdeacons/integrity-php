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

### Batching writes

The write endpoints (member create / update / compliance and the intergroup
register / unregister calls) can be sent together in one HTTP call through
WordPress core's `/wp-json/batch/v1`. Requires Integrity 2.1+ on the site.

```php
$result = $client->batch()
    ->updateMember(5, new UpdateMemberRequest(mobileNumber: '07700 900123'))
    ->registerGroup(7, new RegisterGroupRequest(groupId: 12, gsrName: 'John D.'))
    ->registerOfficer(7, new RegisterOfficerRequest(officerId: 3, positionName: 'Chair', officerName: 'Ann B.'))
    ->send();

if (!$result->success) {
    foreach ($result->failures() as $i => $failed) {
        echo "Request {$i}: {$failed->errorCode} — {$failed->errorMessage}\n";
    }
}
```

- `$result->responses` holds one `ApiResponse` per request, in queue order
  (members come back hydrated as `Member`).
- `batch(requireAllValid: true)` runs nothing unless every request passes the
  server's validation; `$result->validationFailed` is then true and only the
  failing requests carry a response.
- A batch holds at most 25 requests (core's default); queueing a 26th throws
  `LengthException`. Pass `maxSize:` if the site raises the limit.
- Reads (`GET`) can't be batched — WordPress core doesn't allow it.

Authentication is a Bearer token sent via the `Authorization` header. See
`client-example.php` for fuller usage across the other endpoints.

## License

MIT © The Bleeding Deacons
