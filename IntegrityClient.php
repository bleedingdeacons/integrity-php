<?php

declare(strict_types=1);

/**
 * Integrity API PHP Client
 *
 * A PHP REST client for the Integrity WordPress plugin API.
 * Provides access to Groups, Meetings, Positions, Members,
 * and Intergroup Meetings from the Unity plugin.
 *
 * Authentication: Bearer token via Authorization header
 * Base URL format: https://your-wordpress-site.com/wp-json/integrity/v1
 *
 * @see https://github.com/your-org/integrity
 */

// ---------------------------------------------------------------------------
// Response DTOs
// ---------------------------------------------------------------------------

/**
 * Rate limit information included in every authenticated response.
 */
class RateLimit
{
    public function __construct(
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $reset,
    ) {}

    public function resetDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . $this->reset);
    }
}

/**
 * Pagination metadata returned in list responses.
 */
class PaginationMeta
{
    public function __construct(
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $totalPages,
    ) {}
}

/**
 * Generic API response wrapping a single item.
 *
 * @template T
 */
class ApiResponse
{
    /**
     * @param bool           $success
     * @param T|null         $data
     * @param string|null    $errorCode
     * @param string|null    $errorMessage
     * @param int            $httpStatus
     * @param RateLimit|null $rateLimit
     */
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly int $httpStatus,
        public readonly ?RateLimit $rateLimit = null,
    ) {}
}

/**
 * Generic API response wrapping a list of items.
 *
 * @template T
 */
class ApiListResponse
{
    /**
     * @param bool               $success
     * @param T[]                $data
     * @param PaginationMeta|null $meta
     * @param string|null        $errorCode
     * @param string|null        $errorMessage
     * @param int                $httpStatus
     * @param RateLimit|null     $rateLimit
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly ?PaginationMeta $meta,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly int $httpStatus,
        public readonly ?RateLimit $rateLimit = null,
    ) {}
}

// ---------------------------------------------------------------------------
// Model classes
// ---------------------------------------------------------------------------

class ContributionOptions
{
    public function __construct(
        public readonly ?string $venmo,
        public readonly ?string $paypal,
        public readonly ?string $square,
        public readonly bool $hasOptions,
    ) {}
}

class Contact
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $updated,
    ) {}
}

class Location
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $address,
        public readonly string $city,
        public readonly string $state,
        public readonly string $postalCode,
        public readonly string $country,
        public readonly string $region,
        public readonly string $notes,
        public readonly string $link,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly string $timezone,
        public readonly string $formattedAddress,
        public readonly string $updated,
    ) {}
}

class Meeting
{
    /** @param Contact[] $contacts */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?Location $location,
        public readonly string $url,
        public readonly ?int $day,
        public readonly string $dayOfWeek,
        public readonly string $time,
        public readonly string $endTime,
        public readonly array $types,
        public readonly string $state,
        public readonly bool $isOnline,
        public readonly string $onlineLink,
        public readonly string $onlineNotes,
        public readonly array $contacts,
        public readonly array $meta,
        public readonly string $updated,
    ) {}
}

class Group
{
    /**
     * @param Contact[]             $contacts
     * @param int[]|Meeting[]       $meetingIds  IDs or full Meeting objects depending on expand flag
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $website,
        public readonly string $link,
        public readonly string $notes,
        public readonly string $groupEmail,
        public readonly ?int $districtId,
        public readonly string $lastContact,
        public readonly array $meetingIds,
        public readonly array $contacts,
        public readonly ContributionOptions $contributionOptions,
        public readonly string $updated,
    ) {}
}

class Position
{
    public function __construct(
        public readonly int $id,
        public readonly string $longName,
        public readonly string $shortDescription,
        public readonly string $summary,
        public readonly string $email,
        public readonly ?int $minimumSobriety,
        public readonly ?int $termYears,
        public readonly string $link,
        public readonly string $updated,
    ) {}
}

class Member
{
    public function __construct(
        public readonly int $id,
        public readonly string $anonymousName,
        public readonly string $personalEmail,
        public readonly string $mobileNumber,
        public readonly bool $showAnonymousName,
        public readonly bool $showMemberProfile,
        public readonly string $anonymousProfile,
        public readonly ?int $homeGroupId,
        public readonly string $homeGroupName,
        public readonly bool $isGsr,
        public readonly string $meetingPo,
        public readonly ?int $intergroupPositionId,
        public readonly string $intergroupPositionName,
        public readonly string $intergroupPositionRotation,
        public readonly string $link,
        public readonly string $updated,
        /**
         * GDPR compliance state for the member.
         *
         * Null when talking to a server that pre-dates the compliance
         * endpoint and therefore omits the gdpr_compliance object from
         * its response. New servers always populate it.
         */
        public readonly ?GdprCompliance $gdprCompliance = null,
        /**
         * The member's landline number.
         *
         * Masked like the mobile unless the key holds `members:clear`, and
         * empty against a server that pre-dates the field.
         *
         * Appended rather than placed beside mobileNumber for the same
         * reason gdprCompliance was: every parameter before it is required,
         * so inserting mid-list would rebind every positional argument
         * after it.
         */
        public readonly string $landlineNumber = '',
        /**
         * Which of the two numbers to ring — "Mobile" or "Landline".
         *
         * Never masked: it names an option rather than a number. A member
         * with no landline is always "Mobile"; the server settles that, so
         * this can be read at face value. Left a string rather than an enum
         * so a value from a newer server degrades instead of throwing.
         */
        public readonly string $preferredContact = 'Mobile',
    ) {}
}

/**
 * GDPR compliance state recorded for a member.
 *
 * `acceptedAt` is the raw ISO 8601 string returned by the server (e.g.
 * "2026-04-27T15:45:00.000Z") or an empty string when no acceptance
 * has ever been recorded. Callers that need a DateTimeImmutable should
 * parse with `new DateTimeImmutable($member->gdprCompliance->acceptedAt)`
 * after a non-empty check.
 *
 * When `accepted` is false, `version`, `method`, and `statement` are
 * cleared by the server; only `accepted` and `acceptedAt` carry
 * meaningful values for revocations.
 */
class GdprCompliance
{
    public function __construct(
        public readonly bool $accepted,
        public readonly string $acceptedAt,
        public readonly string $version,
        public readonly string $method,
        public readonly string $statement,
    ) {}
}

class GroupAttendee
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}

class OfficerAttendee
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}

class IntergroupMeeting
{
    /**
     * @param int[]             $groupAttendeeIds
     * @param GroupAttendee[]   $groupAttendees
     * @param int[]             $officersAttendingIds
     * @param OfficerAttendee[] $officersAttending
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $date,
        public readonly array $groupAttendeeIds,
        public readonly array $groupAttendees,
        public readonly array $officersAttendingIds,
        public readonly array $officersAttending,
        public readonly string $updated,
    ) {}
}

class HealthStatus
{
    public function __construct(
        public readonly string $status,
        public readonly string $timestamp,
        public readonly string $version,
        public readonly bool $unityAvailable,
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }
}

// ---------------------------------------------------------------------------
// Request parameter objects
// ---------------------------------------------------------------------------

class GroupsQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 100,
        public ?string $search = null,
        public ?int $districtId = null,
        public bool $expandMeetings = false,
    ) {}
}

class MeetingsQuery
{
    /**
     * @param int|null  $day     0 = Sunday … 6 = Saturday
     * @param bool|null $online  true = online only, false = in-person only
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 100,
        public ?int $day = null,
        public ?bool $online = null,
        public ?int $groupId = null,
        public ?string $search = null,
    ) {}
}

class PositionsQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 100,
        public ?string $search = null,
    ) {}
}

class MembersQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 100,
        public ?string $search = null,
        public ?int $homeGroupId = null,
    ) {}
}

class IntergroupMeetingsQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 100,
        public ?string $dateFrom = null, // Y-m-d
        public ?string $dateTo = null,   // Y-m-d
    ) {}
}

class UpdateMemberRequest
{
    public function __construct(
        public ?string $anonymousName = null,
        public ?string $personalEmail = null,
        public ?string $mobileNumber = null,
        public ?bool $showAnonymousName = null,
        public ?bool $showMemberProfile = null,
        public ?string $anonymousProfile = null,
        public ?int $homeGroupId = null,
        public ?bool $isGsr = null,
        public ?int $intergroupPositionId = null,
        public ?string $intergroupPositionRotation = null, // Y-m-d or empty string
        public ?string $landlineNumber = null,
        // "Mobile" or "Landline". The server rejects anything else, and
        // ignores this entirely for a member with no landline.
        public ?string $preferredContact = null,
    ) {}

    /**
     * The JSON body for POST /members/{id}/update — supplied fields only.
     *
     * @return array<string,mixed>
     */
    public function toPayload(): array
    {
        $payload = [];

        if ($this->anonymousName !== null) {
            $payload['anonymous_name'] = $this->anonymousName;
        }
        if ($this->personalEmail !== null) {
            $payload['personal_email'] = $this->personalEmail;
        }
        if ($this->mobileNumber !== null) {
            $payload['mobile_number'] = $this->mobileNumber;
        }
        if ($this->showAnonymousName !== null) {
            $payload['show_anonymous_name'] = $this->showAnonymousName;
        }
        if ($this->showMemberProfile !== null) {
            $payload['show_member_profile'] = $this->showMemberProfile;
        }
        if ($this->anonymousProfile !== null) {
            $payload['anonymous_profile'] = $this->anonymousProfile;
        }
        if ($this->homeGroupId !== null) {
            $payload['home_group_id'] = $this->homeGroupId;
        }
        if ($this->isGsr !== null) {
            $payload['is_gsr'] = $this->isGsr;
        }
        if ($this->intergroupPositionId !== null) {
            $payload['intergroup_position_id'] = $this->intergroupPositionId;
        }
        if ($this->intergroupPositionRotation !== null) {
            $payload['intergroup_position_rotation'] = $this->intergroupPositionRotation;
        }
        if ($this->landlineNumber !== null) {
            $payload['landline_number'] = $this->landlineNumber;
        }
        if ($this->preferredContact !== null) {
            $payload['preferred_contact'] = $this->preferredContact;
        }

        return $payload;
    }
}

class CreateMemberRequest
{
    public function __construct(
        public string $anonymousName,
        public ?string $personalEmail = null,
        public ?string $mobileNumber = null,
        public ?int $homeGroupId = null,
        public ?bool $isGsr = null,
        public ?int $intergroupPositionId = null,
        public ?string $landlineNumber = null,
        // "Mobile" or "Landline"; see UpdateMemberRequest.
        public ?string $preferredContact = null,
    ) {}

    /**
     * The JSON body for POST /members/create.
     *
     * @return array<string,mixed>
     */
    public function toPayload(): array
    {
        $payload = ['anonymous_name' => $this->anonymousName];

        if ($this->personalEmail !== null) {
            $payload['personal_email'] = $this->personalEmail;
        }
        if ($this->mobileNumber !== null) {
            $payload['mobile_number'] = $this->mobileNumber;
        }
        if ($this->homeGroupId !== null) {
            $payload['home_group_id'] = $this->homeGroupId;
        }
        if ($this->isGsr !== null) {
            $payload['is_gsr'] = $this->isGsr;
        }
        if ($this->intergroupPositionId !== null) {
            $payload['intergroup_position_id'] = $this->intergroupPositionId;
        }
        if ($this->landlineNumber !== null) {
            $payload['landline_number'] = $this->landlineNumber;
        }
        if ($this->preferredContact !== null) {
            $payload['preferred_contact'] = $this->preferredContact;
        }

        return $payload;
    }
}

/**
 * Request body for POST /members/{id}/compliance.
 *
 * `accepted` is required (it's the action being recorded — either
 * acceptance or revocation). All other fields are optional and have
 * sensible server-side defaults:
 *
 *   - `acceptedAt`  — defaults to "now" (UTC) when null. Accepts any
 *                     DateTime-parseable string; the server normalises
 *                     to UTC `Y-m-d H:i:s` before storage.
 *   - `version`     — only persisted when `accepted` is true.
 *   - `method`      — defaults to "api" when null and `accepted` is true.
 *                     Ignored on revocations.
 *   - `policyId`    — WordPress post ID of the privacy policy the member
 *                     accepted. The server looks it up through Scrutiny's
 *                     PrivacyPolicyRepository and persists the policy body
 *                     as the recorded statement. Only persisted when
 *                     `accepted` is true; ignored on revocations.
 *                     Replaces the previous `statement` field — clients
 *                     no longer send the policy body on the wire.
 *
 * On revocation (`accepted = false`), the server clears version,
 * method, and statement regardless of what is sent — they belonged
 * to the now-revoked acceptance and no longer apply.
 */
class RecordComplianceRequest
{
    public function __construct(
        public bool $accepted,
        public ?string $acceptedAt = null,
        public ?string $version = null,
        public ?string $method = null,
        public ?int $policyId = null,
    ) {}

    /**
     * The JSON body for POST /members/{id}/compliance.
     *
     * @return array<string,mixed>
     */
    public function toPayload(): array
    {
        $payload = ['accepted' => $this->accepted];

        if ($this->acceptedAt !== null) {
            $payload['accepted_at'] = $this->acceptedAt;
        }
        if ($this->version !== null) {
            $payload['version'] = $this->version;
        }
        if ($this->method !== null) {
            $payload['method'] = $this->method;
        }
        if ($this->policyId !== null) {
            $payload['policy_id'] = $this->policyId;
        }

        return $payload;
    }
}

class RegisterGroupRequest
{
    public function __construct(
        public int $groupId,
        public string $gsrName,
        public int $memberId = 0,
        public bool $gsrProxy = false,
        public string $gsrProxyName = '',
    ) {}

    /** @return array<string,mixed> */
    public function toPayload(): array
    {
        return [
            'group_id' => $this->groupId,
            'gsr_name' => $this->gsrName,
            'member_id' => $this->memberId,
            'gsr_proxy' => $this->gsrProxy,
            'gsr_proxy_name' => $this->gsrProxyName,
        ];
    }
}

class RegisterOfficerRequest
{
    public function __construct(
        public int $officerId,
        public string $positionName,
        public string $officerName,
    ) {}

    /** @return array<string,mixed> */
    public function toPayload(): array
    {
        return [
            'officer_id' => $this->officerId,
            'position_name' => $this->positionName,
            'officer_name' => $this->officerName,
        ];
    }
}

// ---------------------------------------------------------------------------
// Batch
// ---------------------------------------------------------------------------

/**
 * The result of a batch sent through WordPress core's /batch/v1.
 *
 * `responses` holds one ApiResponse per queued request, in the order they
 * were queued. When the batch was sent with `requireAllValid` and any request
 * failed validation, nothing ran: `validationFailed` is true, the requests
 * that failed carry their error, and the rest are null.
 *
 * `errorCode` / `errorMessage` are set only when the batch as a whole was
 * refused (e.g. more requests than the server's batch limit), in which case
 * `responses` is empty.
 */
class BatchResponse
{
    /**
     * @param list<ApiResponse|null> $responses
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $responses,
        public readonly bool $validationFailed,
        public readonly int $httpStatus,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * The responses that did not succeed, keyed by their position in the batch.
     *
     * @return array<int, ApiResponse>
     */
    public function failures(): array
    {
        return array_filter(
            $this->responses,
            static fn (?ApiResponse $r): bool => $r !== null && !$r->success,
        );
    }
}

/**
 * A set of write requests sent to the server in one HTTP call.
 *
 * Only the write endpoints can be batched — WordPress core accepts POST, PUT,
 * PATCH and DELETE in a batch, never GET. Build one with
 * IntegrityClient::batch(), queue requests, then send():
 *
 *     $result = $client->batch()
 *         ->updateMember(5, new UpdateMemberRequest(mobileNumber: '07700 900123'))
 *         ->registerGroup(7, new RegisterGroupRequest(groupId: 12, gsrName: 'John D.'))
 *         ->send();
 *
 * The API key is sent once, on the batch, and the server validates it once.
 * Each request is still checked against its own permission and counts once
 * against the key's rate limit.
 */
class IntegrityBatch implements \Countable
{
    /**
     * The batch limit the Integrity plugin sets on the site. Core's own
     * default is 25; Integrity raises it through rest_get_max_batch_size.
     */
    public const DEFAULT_MAX_SIZE = 256;

    /** @var list<array{path: string, body: array<string,mixed>, member: bool}> */
    private array $requests = [];

    /**
     * @param bool $requireAllValid Run nothing unless every request passes the
     *                              server's argument validation.
     * @param int  $maxSize         Lower only if the site overrides Integrity's limit.
     */
    public function __construct(
        private readonly IntegrityClient $client,
        private readonly bool $requireAllValid = false,
        private readonly int $maxSize = self::DEFAULT_MAX_SIZE,
    ) {}

    public function updateMember(int $id, UpdateMemberRequest $req): static
    {
        return $this->add("/members/{$id}/update", $req->toPayload(), member: true);
    }

    public function createMember(CreateMemberRequest $req): static
    {
        return $this->add('/members/create', $req->toPayload(), member: true);
    }

    public function recordCompliance(int $id, RecordComplianceRequest $req): static
    {
        return $this->add("/members/{$id}/compliance", $req->toPayload(), member: true);
    }

    public function registerGroup(int $meetingId, RegisterGroupRequest $req): static
    {
        return $this->add("/intergroup-meetings/{$meetingId}/register-group", $req->toPayload());
    }

    public function unregisterGroup(int $meetingId, int $groupId): static
    {
        return $this->add("/intergroup-meetings/{$meetingId}/unregister-group", ['group_id' => $groupId]);
    }

    public function registerOfficer(int $meetingId, RegisterOfficerRequest $req): static
    {
        return $this->add("/intergroup-meetings/{$meetingId}/register-officer", $req->toPayload());
    }

    public function unregisterOfficer(int $meetingId, int $officerId): static
    {
        return $this->add("/intergroup-meetings/{$meetingId}/unregister-officer", ['officer_id' => $officerId]);
    }

    public function count(): int
    {
        return count($this->requests);
    }

    /**
     * Send every queued request in one call.
     *
     * @throws IntegrityClientException On a transport or decoding failure
     */
    public function send(): BatchResponse
    {
        return $this->client->sendBatch($this->requests, $this->requireAllValid);
    }

    /**
     * @param array<string,mixed> $body
     */
    private function add(string $path, array $body, bool $member = false): static
    {
        if (count($this->requests) >= $this->maxSize) {
            throw new \LengthException(
                "A batch holds at most {$this->maxSize} requests; send this one and start another."
            );
        }

        $this->requests[] = ['path' => $path, 'body' => $body, 'member' => $member];

        return $this;
    }
}

// ---------------------------------------------------------------------------
// Exception
// ---------------------------------------------------------------------------

class IntegrityClientException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?string $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }
}

// ---------------------------------------------------------------------------
// Main client
// ---------------------------------------------------------------------------

class IntegrityClient
{
    private const REST_ROOT = '/wp-json';
    private const API_NAMESPACE = 'integrity/v1';
    private const API_PATH = self::REST_ROOT . '/' . self::API_NAMESPACE;
    private const DEFAULT_TIMEOUT = 30;

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private ?\CurlHandle $curlHandle = null;

    /**
     * @param string $baseUrl  WordPress site root URL, e.g. "https://example.com"
     * @param string $apiKey   Integrity API key starting with "int_"
     * @param int    $timeout  HTTP request timeout in seconds
     */
    public function __construct(string $baseUrl, string $apiKey, int $timeout = self::DEFAULT_TIMEOUT)
    {
        if (empty($baseUrl)) {
            throw new \InvalidArgumentException('baseUrl must not be empty');
        }
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('apiKey must not be empty');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    public function __destruct()
    {
        $this->close();
    }

    /** Release the cURL handle if open. */
    public function close(): void
    {
        if ($this->curlHandle !== null) {
            curl_close($this->curlHandle);
            $this->curlHandle = null;
        }
    }

    // -----------------------------------------------------------------------
    // Health
    // -----------------------------------------------------------------------

    /**
     * Check API health. Does not require authentication.
     *
     * @return ApiResponse<HealthStatus>
     */
    public function checkHealth(): ApiResponse
    {
        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', '/health');

        $health = new HealthStatus(
            status: $body['status'] ?? 'unknown',
            timestamp: $body['timestamp'] ?? '',
            version: $body['version'] ?? '',
            unityAvailable: (bool) ($body['unity_available'] ?? false),
        );

        return new ApiResponse(
            success: $status < 400,
            data: $health,
            errorCode: null,
            errorMessage: null,
            httpStatus: $status,
            rateLimit: null,
        );
    }

    // -----------------------------------------------------------------------
    // Groups
    // -----------------------------------------------------------------------

    /**
     * List groups with optional filtering and pagination.
     *
     * @return ApiListResponse<Group>
     */
    public function getGroups(GroupsQuery $query = new GroupsQuery()): ApiListResponse
    {
        $params = [
            'page' => $query->page,
            'per_page' => $query->perPage,
        ];

        if ($query->search !== null) {
            $params['search'] = $query->search;
        }
        if ($query->districtId !== null) {
            $params['district_id'] = $query->districtId;
        }
        if ($query->expandMeetings) {
            $params['expand'] = 'meetings';
        }

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', '/groups', $params);

        return $this->buildListResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateGroup($item),
        );
    }

    /**
     * Get a single group by ID.
     *
     * @return ApiResponse<Group>
     */
    public function getGroup(int $id, bool $expandMeetings = false): ApiResponse
    {
        $params = $expandMeetings ? ['expand' => 'meetings'] : [];
        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', "/groups/{$id}", $params);

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateGroup($item),
        );
    }

    // -----------------------------------------------------------------------
    // Meetings
    // -----------------------------------------------------------------------

    /**
     * List meetings with optional filtering and pagination.
     *
     * @return ApiListResponse<Meeting>
     */
    public function getMeetings(MeetingsQuery $query = new MeetingsQuery()): ApiListResponse
    {
        $params = [
            'page' => $query->page,
            'per_page' => $query->perPage,
        ];

        if ($query->day !== null) {
            $params['day'] = $query->day;
        }
        if ($query->online !== null) {
            $params['online'] = $query->online ? 'true' : 'false';
        }
        if ($query->groupId !== null) {
            $params['group_id'] = $query->groupId;
        }
        if ($query->search !== null) {
            $params['search'] = $query->search;
        }

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', '/meetings', $params);

        return $this->buildListResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateMeeting($item),
        );
    }

    /**
     * Get a single meeting by ID.
     *
     * @return ApiResponse<Meeting>
     */
    public function getMeeting(int $id): ApiResponse
    {
        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', "/meetings/{$id}");

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateMeeting($item),
        );
    }

    // -----------------------------------------------------------------------
    // Positions
    // -----------------------------------------------------------------------

    /**
     * List positions with optional filtering and pagination.
     *
     * @return ApiListResponse<Position>
     */
    public function getPositions(PositionsQuery $query = new PositionsQuery()): ApiListResponse
    {
        $params = [
            'page' => $query->page,
            'per_page' => $query->perPage,
        ];

        if ($query->search !== null) {
            $params['search'] = $query->search;
        }

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', '/positions', $params);

        return $this->buildListResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydratePosition($item),
        );
    }

    /**
     * Get a single position by ID.
     *
     * @return ApiResponse<Position>
     */
    public function getPosition(int $id): ApiResponse
    {
        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', "/positions/{$id}");

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydratePosition($item),
        );
    }

    // -----------------------------------------------------------------------
    // Members
    // -----------------------------------------------------------------------

    /**
     * List members with optional filtering and pagination.
     *
     * @return ApiListResponse<Member>
     */
    public function getMembers(MembersQuery $query = new MembersQuery()): ApiListResponse
    {
        $params = [
            'page' => $query->page,
            'per_page' => $query->perPage,
        ];

        if ($query->search !== null) {
            $params['search'] = $query->search;
        }
        if ($query->homeGroupId !== null) {
            $params['home_group_id'] = $query->homeGroupId;
        }

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', '/members', $params);

        return $this->buildListResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateMember($item),
        );
    }

    /**
     * Get a single member by ID.
     *
     * @return ApiResponse<Member>
     */
    public function getMember(int $id): ApiResponse
    {
        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', "/members/{$id}");

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateMember($item),
        );
    }

    /**
     * Partially update a member (only supplied fields are changed).
     *
     * @return ApiResponse<Member>
     */
    public function updateMember(int $id, UpdateMemberRequest $updateRequest): ApiResponse
    {
        $payload = $updateRequest->toPayload();

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('POST', "/members/{$id}/update", body: $payload);

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateMember($item),
        );
    }

    /**
     * Create a new member.
     *
     * @return ApiResponse<Member>
     */
    public function createMember(CreateMemberRequest $createRequest): ApiResponse
    {
        $payload = $createRequest->toPayload();

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('POST', '/members/create', body: $payload);

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateMember($item),
        );
    }

    /**
     * Record a member's GDPR compliance state (acceptance or revocation).
     *
     * Maps to POST /members/{id}/compliance. Requires the `members:write`
     * permission. Returns the full member object with the updated
     * `gdprCompliance` populated.
     *
     * Optional fields are only included in the request body when set;
     * the server applies its own defaults to anything left null.
     *
     * @return ApiResponse<Member>
     */
    public function recordCompliance(int $id, RecordComplianceRequest $req): ApiResponse
    {
        $payload = $req->toPayload();

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('POST', "/members/{$id}/compliance", body: $payload);

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateMember($item),
        );
    }

    // -----------------------------------------------------------------------
    // Intergroup Meetings
    // -----------------------------------------------------------------------

    /**
     * List intergroup meetings with optional date filtering and pagination.
     *
     * @return ApiListResponse<IntergroupMeeting>
     */
    public function getIntergroupMeetings(
        IntergroupMeetingsQuery $query = new IntergroupMeetingsQuery(),
    ): ApiListResponse {
        $params = [
            'page' => $query->page,
            'per_page' => $query->perPage,
        ];

        if ($query->dateFrom !== null) {
            $params['date_from'] = $query->dateFrom;
        }
        if ($query->dateTo !== null) {
            $params['date_to'] = $query->dateTo;
        }

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', '/intergroup-meetings', $params);

        return $this->buildListResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateIntergroupMeeting($item),
        );
    }

    /**
     * Get a single intergroup meeting by ID.
     *
     * @return ApiResponse<IntergroupMeeting>
     */
    public function getIntergroupMeeting(int $id): ApiResponse
    {
        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('GET', "/intergroup-meetings/{$id}");

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $this->hydrateIntergroupMeeting($item),
        );
    }

    /**
     * Register a group as an attendee of an intergroup meeting.
     *
     * @return ApiResponse<array<string,mixed>>
     */
    public function registerGroup(int $meetingId, RegisterGroupRequest $req): ApiResponse
    {
        $payload = $req->toPayload();

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('POST', "/intergroup-meetings/{$meetingId}/register-group", body: $payload);

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $item,
        );
    }

    /**
     * Unregister a group from an intergroup meeting.
     *
     * @return ApiResponse<array<string,mixed>>
     */
    public function unregisterGroup(int $meetingId, int $groupId): ApiResponse
    {
        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('POST', "/intergroup-meetings/{$meetingId}/unregister-group", body: [
                'group_id' => $groupId,
            ]);

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $item,
        );
    }

    /**
     * Register an officer as an attendee of an intergroup meeting.
     *
     * @return ApiResponse<array<string,mixed>>
     */
    public function registerOfficer(int $meetingId, RegisterOfficerRequest $req): ApiResponse
    {
        $payload = $req->toPayload();

        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('POST', "/intergroup-meetings/{$meetingId}/register-officer", body: $payload);

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $item,
        );
    }

    /**
     * Unregister an officer from an intergroup meeting.
     *
     * @return ApiResponse<array<string,mixed>>
     */
    public function unregisterOfficer(int $meetingId, int $officerId): ApiResponse
    {
        ['body' => $body, 'status' => $status, 'headers' => $headers] =
            $this->request('POST', "/intergroup-meetings/{$meetingId}/unregister-officer", body: [
                'officer_id' => $officerId,
            ]);

        return $this->buildSingleResponse(
            $body,
            $status,
            $headers,
            fn (array $item) => $item,
        );
    }

    // -----------------------------------------------------------------------
    // Batch
    // -----------------------------------------------------------------------

    /**
     * Start a batch of write requests. See IntegrityBatch.
     *
     * @param bool $requireAllValid Run nothing unless every request validates.
     * @param int  $maxSize         The site's batch limit, if it overrides Integrity's 256.
     */
    public function batch(bool $requireAllValid = false, int $maxSize = IntegrityBatch::DEFAULT_MAX_SIZE): IntegrityBatch
    {
        return new IntegrityBatch($this, $requireAllValid, $maxSize);
    }

    /**
     * Send queued requests to /wp-json/batch/v1. Called by IntegrityBatch::send().
     *
     * @internal
     * @param  list<array{path: string, body: array<string,mixed>, member: bool}> $requests
     * @throws IntegrityClientException
     */
    public function sendBatch(array $requests, bool $requireAllValid = false): BatchResponse
    {
        if ($requests === []) {
            return new BatchResponse(success: true, responses: [], validationFailed: false, httpStatus: 0);
        }

        $payload = [
            'validation' => $requireAllValid ? 'require-all-validate' : 'normal',
            'requests' => array_map(
                static fn (array $r): array => [
                    'method' => 'POST',
                    'path' => '/' . trim(self::API_NAMESPACE, '/') . $r['path'],
                    // An empty array would encode as [] where core expects an object.
                    'body' => (object) $r['body'],
                ],
                $requests,
            ),
        ];

        ['body' => $body, 'status' => $status] =
            $this->request('POST', '/batch/v1', body: $payload, apiRoot: self::REST_ROOT);

        // Anything but 207 Multi-Status means the batch itself was refused.
        if (!isset($body['responses']) || !is_array($body['responses'])) {
            return new BatchResponse(
                success: false,
                responses: [],
                validationFailed: false,
                httpStatus: $status,
                errorCode: $body['error']['code'] ?? $body['code'] ?? null,
                errorMessage: $body['error']['message'] ?? $body['message'] ?? null,
            );
        }

        $validationFailed = ($body['failed'] ?? null) === 'validation';
        $responses = [];
        $allSucceeded = !$validationFailed;

        foreach ($requests as $i => $request) {
            $item = $body['responses'][$i] ?? null;

            if (!is_array($item)) {
                $responses[] = null;
                $allSucceeded = false;
                continue;
            }

            $headers = array_change_key_case(
                array_map(
                    static fn ($v): string => is_array($v) ? implode(', ', $v) : (string) $v,
                    is_array($item['headers'] ?? null) ? $item['headers'] : [],
                ),
                CASE_LOWER,
            );

            $response = $this->buildSingleResponse(
                is_array($item['body'] ?? null) ? $item['body'] : [],
                (int) ($item['status'] ?? 0),
                $headers,
                $request['member']
                    ? fn (array $data) => $this->hydrateMember($data)
                    : fn (array $data) => $data,
            );

            $allSucceeded = $allSucceeded && $response->success;
            $responses[] = $response;
        }

        return new BatchResponse(
            success: $allSucceeded,
            responses: $responses,
            validationFailed: $validationFailed,
            httpStatus: $status,
        );
    }

    // -----------------------------------------------------------------------
    // HTTP layer
    // -----------------------------------------------------------------------

    /**
     * Execute an HTTP request and return the decoded body, status, and headers.
     *
     * @param  string               $method  GET | POST
     * @param  string               $path    API path without base, e.g. "/groups"
     * @param  array<string,mixed>  $query   Query-string parameters (GET)
     * @param  array<string,mixed>  $body    JSON body parameters (POST)
     * @param  string               $apiRoot Prefix after the site URL; the batch
     *                                       endpoint lives under core's root
     * @return array{body: array<string,mixed>, status: int, headers: array<string,string>}
     * @throws IntegrityClientException
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        string $apiRoot = self::API_PATH,
    ): array {
        $url = $this->baseUrl . $apiRoot . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        // Lazy-initialise a persistent cURL handle for connection reuse
        if ($this->curlHandle === null) {
            $ch = curl_init();
            if ($ch === false) {
                throw new IntegrityClientException('Failed to initialise cURL');
            }
            $this->curlHandle = $ch;
        }

        $ch = $this->curlHandle;

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: IntegrityPHPClient/1.0',
            ],
            CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$responseHeaders): int {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            },
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $raw = curl_exec($ch);

        if ($raw === false) {
            throw new IntegrityClientException(
                'cURL error: ' . curl_error($ch),
                curl_errno($ch),
            );
        }

        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        try {
            /** @var array<string,mixed> $decoded */
            $decoded = json_decode((string) $raw, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new IntegrityClientException(
                'Failed to decode JSON response: ' . $e->getMessage(),
                $httpStatus,
                previous: $e,
            );
        }

        return ['body' => $decoded, 'status' => $httpStatus, 'headers' => $responseHeaders];
    }

    // -----------------------------------------------------------------------
    // Response builders
    // -----------------------------------------------------------------------

    /**
     * @template T
     * @param  array<string,mixed>      $body
     * @param  array<string,string>     $headers
     * @param  callable(array): T       $hydrator
     * @return ApiResponse<T>
     */
    private function buildSingleResponse(
        array $body,
        int $status,
        array $headers,
        callable $hydrator,
    ): ApiResponse {
        $rateLimit = $this->extractRateLimit($headers);

        if (!($body['success'] ?? false)) {
            // Integrity's own errors are {success, error: {code, message}};
            // a refusal from the permission check, and any error inside a
            // batch, arrives in WordPress's {code, message, data} shape.
            return new ApiResponse(
                success: false,
                data: null,
                errorCode: $body['error']['code'] ?? $body['code'] ?? null,
                errorMessage: $body['error']['message'] ?? $body['message'] ?? null,
                httpStatus: $status,
                rateLimit: $rateLimit,
            );
        }

        return new ApiResponse(
            success: true,
            data: $hydrator($body['data'] ?? []),
            errorCode: null,
            errorMessage: null,
            httpStatus: $status,
            rateLimit: $rateLimit,
        );
    }

    /**
     * @template T
     * @param  array<string,mixed>      $body
     * @param  array<string,string>     $headers
     * @param  callable(array): T       $hydrator
     * @return ApiListResponse<T>
     */
    private function buildListResponse(
        array $body,
        int $status,
        array $headers,
        callable $hydrator,
    ): ApiListResponse {
        $rateLimit = $this->extractRateLimit($headers);

        if (!($body['success'] ?? false)) {
            return new ApiListResponse(
                success: false,
                data: [],
                meta: null,
                errorCode: $body['error']['code'] ?? $body['code'] ?? null,
                errorMessage: $body['error']['message'] ?? $body['message'] ?? null,
                httpStatus: $status,
                rateLimit: $rateLimit,
            );
        }

        $items = array_map($hydrator, $body['data'] ?? []);

        $metaRaw = $body['meta'] ?? null;
        $meta = $metaRaw !== null ? new PaginationMeta(
            total: (int) ($metaRaw['total'] ?? 0),
            page: (int) ($metaRaw['page'] ?? 1),
            perPage: (int) ($metaRaw['per_page'] ?? count($items)),
            totalPages: (int) ($metaRaw['total_pages'] ?? 1),
        ) : null;

        return new ApiListResponse(
            success: true,
            data: $items,
            meta: $meta,
            errorCode: null,
            errorMessage: null,
            httpStatus: $status,
            rateLimit: $rateLimit,
        );
    }

    /**
     * @param array<string,string> $headers
     */
    private function extractRateLimit(array $headers): ?RateLimit
    {
        if (!isset($headers['x-ratelimit-limit'])) {
            return null;
        }

        return new RateLimit(
            limit: (int) ($headers['x-ratelimit-limit'] ?? 0),
            remaining: (int) ($headers['x-ratelimit-remaining'] ?? 0),
            reset: (int) ($headers['x-ratelimit-reset'] ?? 0),
        );
    }

    // -----------------------------------------------------------------------
    // Hydrators — convert raw API arrays to typed model objects
    // -----------------------------------------------------------------------

    /** @param array<string,mixed> $data */
    private function hydrateGroup(array $data): Group
    {
        $contacts = array_map([$this, 'hydrateContact'], $data['contacts'] ?? []);

        $co = $data['contribution_options'] ?? [];
        $contributionOptions = new ContributionOptions(
            venmo: $co['venmo'] ?? null,
            paypal: $co['paypal'] ?? null,
            square: $co['square'] ?? null,
            hasOptions: (bool) ($co['has_options'] ?? false),
        );

        // The API returns either `meeting_ids` (int[]) or `meetings` (full objects)
        // depending on whether `expand=meetings` was requested.
        $rawMeetings = $data['meetings'] ?? $data['meeting_ids'] ?? [];
        $meetingIds = array_map(
            fn ($m) => is_array($m) ? $this->hydrateMeeting($m) : (int) $m,
            $rawMeetings,
        );

        return new Group(
            id: (int) ($data['id'] ?? 0),
            title: (string) ($data['title'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            website: (string) ($data['website'] ?? ''),
            link: (string) ($data['link'] ?? ''),
            notes: (string) ($data['notes'] ?? ''),
            groupEmail: (string) ($data['group_email'] ?? ''),
            districtId: isset($data['district_id']) ? (int) $data['district_id'] : null,
            lastContact: (string) ($data['last_contact'] ?? ''),
            meetingIds: $meetingIds,
            contacts: $contacts,
            contributionOptions: $contributionOptions,
            updated: (string) ($data['updated'] ?? ''),
        );
    }

    /** @param array<string,mixed> $data */
    private function hydrateMeeting(array $data): Meeting
    {
        $contacts = array_map([$this, 'hydrateContact'], $data['contacts'] ?? []);
        $location = isset($data['location']) && is_array($data['location'])
            ? $this->hydrateLocation($data['location'])
            : null;

        return new Meeting(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            location: $location,
            url: (string) ($data['url'] ?? ''),
            day: isset($data['day']) ? (int) $data['day'] : null,
            dayOfWeek: (string) ($data['day_of_week'] ?? ''),
            time: (string) ($data['time'] ?? ''),
            endTime: (string) ($data['end_time'] ?? ''),
            types: (array) ($data['types'] ?? []),
            state: (string) ($data['state'] ?? ''),
            isOnline: (bool) ($data['is_online'] ?? false),
            onlineLink: (string) ($data['online_link'] ?? ''),
            onlineNotes: (string) ($data['online_notes'] ?? ''),
            contacts: $contacts,
            meta: (array) ($data['meta'] ?? []),
            updated: (string) ($data['updated'] ?? ''),
        );
    }

    /** @param array<string,mixed> $data */
    private function hydrateLocation(array $data): Location
    {
        return new Location(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            address: (string) ($data['address'] ?? ''),
            city: (string) ($data['city'] ?? ''),
            state: (string) ($data['state'] ?? ''),
            postalCode: (string) ($data['postal_code'] ?? ''),
            country: (string) ($data['country'] ?? ''),
            region: (string) ($data['region'] ?? ''),
            notes: (string) ($data['notes'] ?? ''),
            link: (string) ($data['link'] ?? ''),
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            timezone: (string) ($data['timezone'] ?? ''),
            formattedAddress: (string) ($data['formatted_address'] ?? ''),
            updated: (string) ($data['updated'] ?? ''),
        );
    }

    /** @param array<string,mixed> $data */
    private function hydrateContact(array $data): Contact
    {
        return new Contact(
            name: (string) ($data['name'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            updated: (string) ($data['updated'] ?? ''),
        );
    }

    /** @param array<string,mixed> $data */
    private function hydratePosition(array $data): Position
    {
        return new Position(
            id: (int) ($data['id'] ?? 0),
            longName: (string) ($data['long_name'] ?? ''),
            shortDescription: (string) ($data['short_description'] ?? ''),
            summary: (string) ($data['summary'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            minimumSobriety: isset($data['minimum_sobriety']) ? (int) $data['minimum_sobriety'] : null,
            termYears: isset($data['term_years']) ? (int) $data['term_years'] : null,
            link: (string) ($data['link'] ?? ''),
            updated: (string) ($data['updated'] ?? ''),
        );
    }

    /** @param array<string,mixed> $data */
    private function hydrateMember(array $data): Member
    {
        // gdpr_compliance was added with the compliance endpoint. Older
        // server versions omit the key entirely — treat that as null
        // rather than synthesising a fake "not accepted" state, which
        // would be indistinguishable from an actively-recorded denial.
        $gdprCompliance = null;
        if (isset($data['gdpr_compliance']) && is_array($data['gdpr_compliance'])) {
            $g = $data['gdpr_compliance'];
            $gdprCompliance = new GdprCompliance(
                accepted:   (bool) ($g['accepted'] ?? false),
                acceptedAt: (string) ($g['accepted_at'] ?? ''),
                version:    (string) ($g['version'] ?? ''),
                method:     (string) ($g['method'] ?? ''),
                statement:  (string) ($g['statement'] ?? ''),
            );
        }

        return new Member(
            id: (int) ($data['id'] ?? 0),
            anonymousName: (string) ($data['anonymous_name'] ?? ''),
            personalEmail: (string) ($data['personal_email'] ?? ''),
            mobileNumber: (string) ($data['mobile_number'] ?? ''),
            showAnonymousName: (bool) ($data['show_anonymous_name'] ?? false),
            showMemberProfile: (bool) ($data['show_member_profile'] ?? false),
            anonymousProfile: (string) ($data['anonymous_profile'] ?? ''),
            homeGroupId: isset($data['home_group_id']) ? (int) $data['home_group_id'] : null,
            homeGroupName: (string) ($data['home_group_name'] ?? ''),
            isGsr: (bool) ($data['is_gsr'] ?? false),
            meetingPo: (string) ($data['meeting_po'] ?? ''),
            intergroupPositionId: isset($data['intergroup_position_id'])
                ? (int) $data['intergroup_position_id']
                : null,
            intergroupPositionName: (string) ($data['intergroup_position_name'] ?? ''),
            intergroupPositionRotation: (string) ($data['intergroup_position_rotation'] ?? ''),
            link: (string) ($data['link'] ?? ''),
            updated: (string) ($data['updated'] ?? ''),
            gdprCompliance: $gdprCompliance,
            landlineNumber: (string) ($data['landline_number'] ?? ''),
            // A server that pre-dates the field sends nothing, and a member
            // with no landline has nothing to choose — "Mobile" is right for
            // both, and matches what the server would have said.
            preferredContact: (string) ($data['preferred_contact'] ?? 'Mobile'),
        );
    }

    /** @param array<string,mixed> $data */
    private function hydrateIntergroupMeeting(array $data): IntergroupMeeting
    {
        $groupAttendees = array_map(
            fn (array $a) => new GroupAttendee(id: (int) $a['id'], name: (string) ($a['name'] ?? '')),
            $data['group_attendees'] ?? [],
        );

        $officersAttending = array_map(
            fn (array $a) => new OfficerAttendee(id: (int) $a['id'], name: (string) ($a['name'] ?? '')),
            $data['officers_attending'] ?? [],
        );

        return new IntergroupMeeting(
            id: (int) ($data['id'] ?? 0),
            title: (string) ($data['title'] ?? ''),
            date: (string) ($data['date'] ?? ''),
            groupAttendeeIds: array_map('intval', $data['group_attendee_ids'] ?? []),
            groupAttendees: $groupAttendees,
            officersAttendingIds: array_map('intval', $data['officers_attending_ids'] ?? []),
            officersAttending: $officersAttending,
            updated: (string) ($data['updated'] ?? ''),
        );
    }
}
