<?php
/**
 * =====================================================================
 * Minimal Firestore REST client
 * ---------------------------------------------------------------------
 * google/cloud-firestore (the official client) requires the PHP grpc
 * extension, which isn't available in this environment. Firestore's
 * REST API covers everything this app needs (reading small collections
 * and single documents), so this talks to it directly over HTTPS using
 * a service-account access token from google/auth + Guzzle — both
 * already pulled in by kreait/firebase-php.
 * =====================================================================
 */

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class FirestoreRestClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/datastore';

    private readonly string $projectId;
    private readonly string $credentialsPath;
    private readonly Client $http;
    private ?string $accessToken = null;

    /**
     * True after any call below that failed for a real reason (network
     * error, Firestore outage, or — the one that actually happened —
     * the free-tier daily quota getting exhausted, a 429
     * RESOURCE_EXHAUSTED). NOT set for getDocument()'s ordinary 404
     * ("this id doesn't exist"), which isn't a failure. Every "get all
     * X" page-data function returns an empty array either way (genuinely
     * zero rows, or a failed request that couldn't fetch any) — callers
     * that need to tell those apart (a page about to render "No
     * patients found") should check this right after calling, since
     * those two situations should never look the same to whoever's
     * looking at the page.
     */
    public bool $lastRequestFailed = false;

    /**
     * Request-scoped cache for listDocuments() — keyed by collection
     * name. $firestore is constructed fresh once per page load (see
     * config/firebase.php), so this never outlives one request and
     * never risks serving stale data to the next one; within a single
     * request it's exactly right, since nothing here needs
     * millisecond-fresh re-reads of a collection a page already read
     * once. Added after a real facility_analytics.php page load was
     * measured calling listDocuments('patients')/('users')/('medicines')
     * more than once each — each one now costs 1 read instead of N.
     * addDocument()/setDocument()/deleteDocument() invalidate a
     * collection's entry on success, so a page that writes then reads
     * back never sees what it had before the write.
     *
     * @var array<string, array<int, array{id: string, fields: array<string, mixed>}>>
     */
    private array $listCache = [];

    /**
     * How long a persisted listDocuments() result stays valid across
     * DIFFERENT requests (see cacheDir()'s comment) before it's treated
     * as stale and re-fetched. $listCache above only helps within one
     * page load; this is what actually cuts quota use across the many
     * separate page loads a real session makes — a page reloaded twice
     * in a minute, or two different pages that both read "patients",
     * now cost 1 real read instead of 2+. 60s balances that against
     * freshness: a newly added patient shows up everywhere within a
     * minute, which is a fine trade for a small clinic system and a
     * free-tier quota that doesn't otherwise survive normal browsing.
     */
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(string $projectId, string $credentialsPath)
    {
        $this->projectId = $projectId;
        $this->credentialsPath = $credentialsPath;
        $this->http = new Client([
            'base_uri' => "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/",
            // A real 300-doc page of "patients"/"medical_records" has been
            // measured taking anywhere from ~3s to over 25s to fully
            // transfer on this network (highly variable, not just a size
            // issue) — 8s was cutting those off mid-download and silently
            // returning an empty result to the page. 45s gives real,
            // slow-but-completing requests room to finish instead of
            // failing fast on a connection that isn't actually dead.
            'timeout' => 45,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * A plain system-temp-dir folder, not the request-scoped $listCache
     * above — this is what makes the cache survive between separate
     * PHP requests (every real page load is its own process/request;
     * an in-memory property never sees the next one). Purely a
     * best-effort cost optimization: any failure to read/write/create
     * it is swallowed and simply falls back to a real Firestore call,
     * never breaks a page.
     */
    private function cacheDir(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/healtrack_firestore_cache';
    }

    private function cacheFilePath(string $collection): string
    {
        // Collection names in this app are always plain identifiers
        // (patients, consultations, ...) but sanitize anyway before it
        // ever touches a filesystem path.
        return $this->cacheDir() . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection) . '.json';
    }

    /** @return array<int, array{id: string, fields: array<string, mixed>}>|null null on a cache miss/expiry/any failure — caller falls back to a real read. */
    private function cacheRead(string $collection): ?array
    {
        $path = $this->cacheFilePath($collection);
        if (!is_file($path) || (time() - (@filemtime($path) ?: 0)) > self::CACHE_TTL_SECONDS) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<int, array{id: string, fields: array<string, mixed>}> $documents */
    private function cacheWrite(string $collection, array $documents): void
    {
        $dir = $this->cacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($this->cacheFilePath($collection), json_encode($documents));
    }

    private function cacheInvalidate(string $collection): void
    {
        @unlink($this->cacheFilePath($collection));
    }

    /**
     * Creates a document with a Firestore-assigned auto ID (a POST to the
     * collection, rather than setDocument()'s PATCH to a known doc path).
     * Used for append-only collections like "audit_logs" where nothing
     * needs to address a specific entry by id afterward.
     *
     * @param array<string, mixed> $fields
     */
    public function addDocument(string $collection, array $fields): ?string
    {
        $this->lastRequestFailed = false;

        try {
            $response = $this->http->post($collection, [
                'headers' => $this->authHeaders(),
                'json' => ['fields' => $this->encodeFields($fields)],
            ]);
        } catch (GuzzleException $e) {
            $this->lastRequestFailed = true;
            error_log('[Firestore] addDocument(' . $collection . ') failed: ' . $e->getMessage());
            return null;
        }

        $data = json_decode((string) $response->getBody(), true);

        unset($this->listCache[$collection]); // in-request cache
        $this->cacheInvalidate($collection);  // persisted cross-request cache

        return isset($data['name']) ? basename($data['name']) : null;
    }

    /**
     * Firestore's documents.list REST endpoint defaults to a page size
     * of 100 and never returns more than that in one response — without
     * following its nextPageToken, this silently truncated every
     * collection over 100 docs to just its first 100, with no error and
     * no indication anything was missing (the actual bug behind the
     * Total Patients stat card reading "100" once the "patients"
     * collection passed that size). Loops pageToken-to-pageToken here
     * so every caller keeps getting the real, complete collection.
     *
     * @return array<int, array{id: string, fields: array<string, mixed>}>
     */
    public function listDocuments(string $collection): array
    {
        if (isset($this->listCache[$collection])) {
            $this->lastRequestFailed = false;
            return $this->listCache[$collection];
        }

        $persisted = $this->cacheRead($collection);
        if ($persisted !== null) {
            $this->lastRequestFailed = false;
            return $this->listCache[$collection] = $persisted;
        }

        $this->lastRequestFailed = false;
        $documents = [];
        $pageToken = null;

        do {
            $query = ['pageSize' => 300];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            try {
                $response = $this->http->get($collection, ['headers' => $this->authHeaders(), 'query' => $query]);
                $data = json_decode((string) $response->getBody(), true);
            } catch (GuzzleException $e) {
                $this->lastRequestFailed = true;
                error_log('[Firestore] listDocuments(' . $collection . ') failed: ' . $e->getMessage());
                return $documents; // never cached — a genuine failure, not real data
            }

            foreach ($data['documents'] ?? [] as $doc) {
                $documents[] = [
                    'id' => basename($doc['name']),
                    'fields' => $this->decodeFields($doc['fields'] ?? []),
                ];
            }

            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        $this->cacheWrite($collection, $documents);

        return $this->listCache[$collection] = $documents;
    }

    /**
     * Runs a real Firestore structured query — where/orderBy/limit/
     * cursor, none of which listDocuments() supports (it's a plain
     * unfiltered GET). Lets a caller ask for "the next 8 patients after
     * this cursor" instead of reading the whole collection and slicing
     * it in PHP. $structuredQuery is the raw StructuredQuery shape
     * Firestore's REST API expects — see
     * https://firebase.google.com/docs/firestore/reference/rest/v1/StructuredQuery
     * Not cached (unlike listDocuments()) — a query result is a small
     * page, not a whole collection, so there's nothing expensive here
     * to save a repeat read on.
     *
     * @return array<int, array{id: string, fields: array<string, mixed>}>
     */
    public function queryDocuments(array $structuredQuery): array
    {
        $this->lastRequestFailed = false;

        try {
            // Note the "../" — this client's base_uri ends in ".../documents/"
            // (for the plain listDocuments()/getDocument() paths below,
            // which address a specific collection/doc under it), but
            // :runQuery is a database-level RPC addressed as
            // ".../documents:runQuery" (no slash before the colon) —
            // relative to the base_uri, that means stepping up one
            // level first.
            $response = $this->http->post('../documents:runQuery', [
                'headers' => $this->authHeaders(),
                'json' => ['structuredQuery' => $structuredQuery],
            ]);
        } catch (GuzzleException $e) {
            $this->lastRequestFailed = true;
            error_log('[Firestore] queryDocuments() failed: ' . $e->getMessage());
            return [];
        }

        $rows = json_decode((string) $response->getBody(), true) ?? [];
        $documents = [];
        foreach ($rows as $row) {
            if (!isset($row['document'])) {
                continue; // a row with no real result (e.g. a trailing empty entry)
            }
            $documents[] = [
                'id' => basename($row['document']['name']),
                'fields' => $this->decodeFields($row['document']['fields'] ?? []),
            ];
        }

        return $documents;
    }

    /**
     * A real server-side COUNT() over $collection (optionally narrowed
     * by $whereFilters, each a raw StructuredQuery Filter — see
     * queryDocuments()'s doc link), via Firestore's :runAggregationQuery
     * RPC. Billed as roughly one read no matter how large the matched
     * set is — the fix for stat cards that used to read(!) an entire
     * collection just to run count() on it in PHP. Returns null (not 0)
     * on a real failure, so a caller can tell "genuinely zero" apart
     * from "couldn't ask Firestore at all" the same way
     * $lastRequestFailed already lets listDocuments() callers do.
     *
     * @param array<int, array<string, mixed>> $whereFilters
     */
    public function countDocuments(string $collection, array $whereFilters = []): ?int
    {
        $this->lastRequestFailed = false;

        $structuredQuery = ['from' => [['collectionId' => $collection]]];
        if ($whereFilters !== []) {
            $structuredQuery['where'] = count($whereFilters) === 1
                ? $whereFilters[0]
                : ['compositeFilter' => ['op' => 'AND', 'filters' => $whereFilters]];
        }

        try {
            $response = $this->http->post('../documents:runAggregationQuery', [
                'headers' => $this->authHeaders(),
                'json' => [
                    'structuredAggregationQuery' => [
                        'structuredQuery' => $structuredQuery,
                        // {} (not [], which json_encode()s to a JSON array,
                        // not an object) — Firestore's Count aggregation
                        // takes no parameters of its own.
                        'aggregations' => [['alias' => 'count', 'count' => new \stdClass()]],
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            $this->lastRequestFailed = true;
            error_log('[Firestore] countDocuments(' . $collection . ') failed: ' . $e->getMessage());
            return null;
        }

        $rows = json_decode((string) $response->getBody(), true) ?? [];
        foreach ($rows as $row) {
            if (isset($row['result']['aggregateFields']['count']['integerValue'])) {
                return (int) $row['result']['aggregateFields']['count']['integerValue'];
            }
        }

        return 0;
    }

    /**
     * A real server-side AVG() over one numeric field of $collection
     * (optionally narrowed by $whereFilters) — same :runAggregationQuery
     * RPC as countDocuments(), same one-read-ish cost regardless of
     * collection size, instead of reading every document just to
     * average one field in PHP. Returns null on a real failure OR when
     * there's nothing to average (an empty match set has no average).
     *
     * NOTE: unlike countDocuments() (a very well-worn, stable Firestore
     * feature), the exact response shape for AVG hasn't been exercised
     * live against this project yet — Firestore's own REST reference
     * documents 'avg' aggregations returning a doubleValue the same way
     * 'count' returns an integerValue, so this follows that, but treat
     * a persistently-null result here as worth double-checking against
     * a real request before assuming the underlying data is genuinely
     * empty.
     *
     * @param array<int, array<string, mixed>> $whereFilters
     */
    public function averageField(string $collection, string $fieldPath, array $whereFilters = []): ?float
    {
        $this->lastRequestFailed = false;

        $structuredQuery = ['from' => [['collectionId' => $collection]]];
        if ($whereFilters !== []) {
            $structuredQuery['where'] = count($whereFilters) === 1
                ? $whereFilters[0]
                : ['compositeFilter' => ['op' => 'AND', 'filters' => $whereFilters]];
        }

        try {
            $response = $this->http->post('../documents:runAggregationQuery', [
                'headers' => $this->authHeaders(),
                'json' => [
                    'structuredAggregationQuery' => [
                        'structuredQuery' => $structuredQuery,
                        'aggregations' => [['alias' => 'avgValue', 'avg' => ['field' => ['fieldPath' => $fieldPath]]]],
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            $this->lastRequestFailed = true;
            error_log('[Firestore] averageField(' . $collection . '.' . $fieldPath . ') failed: ' . $e->getMessage());
            return null;
        }

        $rows = json_decode((string) $response->getBody(), true) ?? [];
        foreach ($rows as $row) {
            $agg = $row['result']['aggregateFields']['avgValue'] ?? null;
            if ($agg === null) {
                continue;
            }
            if (isset($agg['doubleValue'])) {
                return (float) $agg['doubleValue'];
            }
            if (isset($agg['integerValue'])) {
                return (float) $agg['integerValue'];
            }
            if ($agg === []) {
                return null; // an empty match set — nothing to average
            }
        }

        return null;
    }

    /** Builds one raw StructuredQuery equality Filter — the shape queryDocuments()/countDocuments()'s $structuredQuery/$whereFilters expect. */
    /**
     * Builds one raw StructuredQuery Filter — the shape
     * queryDocuments()/countDocuments()'s $structuredQuery/$whereFilters
     * expect. $op is any real StructuredQuery.FieldFilter.Operator
     * value: 'EQUAL', 'GREATER_THAN_OR_EQUAL', 'LESS_THAN', etc. — see
     * https://firebase.google.com/docs/firestore/reference/rest/v1/StructuredQuery#operator
     */
    public function fieldFilter(string $fieldPath, string $op, mixed $value): array
    {
        return ['fieldFilter' => [
            'field' => ['fieldPath' => $fieldPath],
            'op' => $op,
            'value' => $this->encodeValue($value),
        ]];
    }

    /** Shorthand for fieldFilter($fieldPath, 'EQUAL', $value) — the common case. */
    public function fieldEqualsFilter(string $fieldPath, mixed $value): array
    {
        return $this->fieldFilter($fieldPath, 'EQUAL', $value);
    }

    /** @return array<string, mixed>|null */
    public function getDocument(string $collection, string $docId): ?array
    {
        $this->lastRequestFailed = false;

        try {
            $response = $this->http->get("{$collection}/{$docId}", ['headers' => $this->authHeaders()]);
        } catch (GuzzleException $e) {
            if ($e->getCode() !== 404) {
                $this->lastRequestFailed = true;
                error_log('[Firestore] getDocument(' . $collection . '/' . $docId . ') failed: ' . $e->getMessage());
            }
            return null;
        }

        $data = json_decode((string) $response->getBody(), true);

        return $this->decodeFields($data['fields'] ?? []);
    }

    public function deleteDocument(string $collection, string $docId): bool
    {
        $this->lastRequestFailed = false;

        try {
            $this->http->delete("{$collection}/{$docId}", ['headers' => $this->authHeaders()]);
        } catch (GuzzleException $e) {
            $this->lastRequestFailed = true;
            error_log('[Firestore] deleteDocument(' . $collection . '/' . $docId . ') failed: ' . $e->getMessage());
            return false;
        }

        unset($this->listCache[$collection]); // in-request cache
        $this->cacheInvalidate($collection);  // persisted cross-request cache

        return true;
    }

    /**
     * Creates or overwrites a document's fields (upsert).
     *
     * @param array<string, mixed> $fields
     */
    public function setDocument(string $collection, string $docId, array $fields): bool
    {
        $this->lastRequestFailed = false;

        try {
            $this->http->patch("{$collection}/{$docId}", [
                'headers' => $this->authHeaders(),
                'json' => ['fields' => $this->encodeFields($fields)],
            ]);
        } catch (GuzzleException $e) {
            $this->lastRequestFailed = true;
            error_log('[Firestore] setDocument(' . $collection . '/' . $docId . ') failed: ' . $e->getMessage());
            return false;
        }

        unset($this->listCache[$collection]); // in-request cache
        $this->cacheInvalidate($collection);  // persisted cross-request cache

        return true;
    }

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->getAccessToken()];
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $credentials = new ServiceAccountCredentials(self::SCOPE, $this->credentialsPath);
        $token = $credentials->fetchAuthToken();

        return $this->accessToken = $token['access_token'];
    }

    /** @param array<string, mixed> $fields */
    private function decodeFields(array $fields): array
    {
        $decoded = [];
        foreach ($fields as $key => $value) {
            $decoded[$key] = $this->decodeValue($value);
        }

        return $decoded;
    }

    private function decodeValue(array $value): mixed
    {
        return match (true) {
            array_key_exists('stringValue', $value) => $value['stringValue'],
            array_key_exists('integerValue', $value) => (int) $value['integerValue'],
            array_key_exists('doubleValue', $value) => (float) $value['doubleValue'],
            array_key_exists('booleanValue', $value) => $value['booleanValue'],
            array_key_exists('timestampValue', $value) => $value['timestampValue'],
            array_key_exists('nullValue', $value) => null,
            array_key_exists('mapValue', $value) => $this->decodeFields($value['mapValue']['fields'] ?? []),
            array_key_exists('arrayValue', $value) => array_map(
                fn ($v) => $this->decodeValue($v),
                $value['arrayValue']['values'] ?? [],
            ),
            default => null,
        };
    }

    /** @param array<string, mixed> $fields */
    private function encodeFields(array $fields): array
    {
        $encoded = [];
        foreach ($fields as $key => $value) {
            $encoded[$key] = $this->encodeValue($value);
        }

        return $encoded;
    }

    private function encodeValue(mixed $value): array
    {
        return match (true) {
            is_string($value) => ['stringValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            is_bool($value) => ['booleanValue' => $value],
            is_null($value) => ['nullValue' => null],
            is_array($value) && array_is_list($value) => [
                'arrayValue' => ['values' => array_map(fn ($v) => $this->encodeValue($v), $value)],
            ],
            is_array($value) => ['mapValue' => ['fields' => $this->encodeFields($value)]],
            default => ['nullValue' => null],
        };
    }
}
