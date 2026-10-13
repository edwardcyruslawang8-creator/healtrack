<?php
/**
 * =====================================================================
 * Minimal Dialogflow ES REST client
 * ---------------------------------------------------------------------
 * Same reasoning as FirestoreRestClient.php in this folder: the
 * official google/cloud-dialogflow client needs the PHP grpc
 * extension, which isn't available here. Dialogflow ES's detectIntent
 * REST endpoint is all this app needs (NLU only — intent + extracted
 * entities for one message at a time), so this talks to it directly
 * over HTTPS using a service-account access token from google/auth,
 * exactly like FirestoreRestClient does.
 *
 * This client does ONLY intent detection. It has no opinion on what
 * happens next — includes/public/chatbot_decision_tree.php owns all
 * of that logic, using only this client's output as input.
 * =====================================================================
 */

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class DialogflowRestClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/dialogflow';

    private readonly string $credentialsPath;
    private readonly string $languageCode;
    private readonly Client $http;
    private ?string $accessToken = null;

    public function __construct(string $projectId, string $credentialsPath, string $languageCode = 'en')
    {
        $this->credentialsPath = $credentialsPath;
        $this->languageCode = $languageCode;
        $this->http = new Client([
            'base_uri' => "https://dialogflow.googleapis.com/v2/projects/{$projectId}/agent/sessions/",
            'timeout' => 10,
        ]);
    }

    /**
     * Sends one message to the Dialogflow agent and returns its NLU
     * result: which intent matched, how confident it was, and any
     * entities it extracted (e.g. a medicine name). Never throws —
     * network/API failures come back as a null intent so callers (the
     * decision tree) can fall back cleanly, the same way
     * FirestoreRestClient's read methods fail soft rather than throw.
     *
     * @return array{intent: ?string, confidence: float, parameters: array<string, mixed>, queryText: string}
     */
    public function detectIntent(string $sessionId, string $message): array
    {
        $fallback = ['intent' => null, 'confidence' => 0.0, 'parameters' => [], 'queryText' => $message];

        try {
            // Leading "./" forces this to resolve as a relative reference
            // against base_uri — without it, a session id containing no
            // slash before its ":detectIntent" suffix (e.g. a plain hex
            // string) parses per RFC 3986 as an absolute URI with that
            // hex string as the scheme, which Guzzle then rejects.
            $response = $this->http->post('./' . rawurlencode($sessionId) . ':detectIntent', [
                'headers' => $this->authHeaders() + ['Content-Type' => 'application/json'],
                'json' => [
                    'queryInput' => [
                        'text' => [
                            'text' => $message,
                            'languageCode' => $this->languageCode,
                        ],
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            error_log('[Dialogflow] detectIntent failed: ' . $e->getMessage());

            return $fallback;
        }

        $body = json_decode((string) $response->getBody(), true);
        $result = $body['queryResult'] ?? null;
        if (!is_array($result)) {
            return $fallback;
        }

        return [
            'intent' => $result['intent']['displayName'] ?? null,
            'confidence' => (float) ($result['intentDetectionConfidence'] ?? 0.0),
            'parameters' => is_array($result['parameters'] ?? null) ? $result['parameters'] : [],
            'queryText' => (string) ($result['queryText'] ?? $message),
        ];
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
}
