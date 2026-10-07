<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use ITuoiProfessionistiDigitali\Connector\Data\AcceptedEventData;
use ITuoiProfessionistiDigitali\Connector\Data\EnvelopeData;
use ITuoiProfessionistiDigitali\Connector\Data\EventTypeData;
use ITuoiProfessionistiDigitali\Connector\Data\ListedMemberData;
use ITuoiProfessionistiDigitali\Connector\Data\MemberData;
use ITuoiProfessionistiDigitali\Connector\Data\MemberPage;
use ITuoiProfessionistiDigitali\Connector\Data\RegisteredMemberData;
use ITuoiProfessionistiDigitali\Connector\Data\SystemData;
use ITuoiProfessionistiDigitali\Connector\Data\TypologyData;
use ITuoiProfessionistiDigitali\Connector\Enums\SystemStatus;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformNotConfiguredException;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformRequestException;
use ITuoiProfessionistiDigitali\Connector\Validation\PayloadValidator;
use Throwable;

/**
 * Talks to the platform on behalf of the system whose credentials are configured.
 *
 * Every method throws PlatformNotConfiguredException without credentials, and PlatformRequestException
 * when the platform refuses the request.
 */
final readonly class PlatformClient
{
    private const string CATALOGUE_CACHE_KEY = 'platform.event-types';

    public function __construct(
        private Http $http,
        private Cache $cache,
        private ConnectorConfig $config,
    ) {}

    /**
     * GET /system: the system as the platform sees it.
     */
    public function system(): SystemData
    {
        return SystemData::from($this->data($this->request('GET', 'system')));
    }

    /**
     * PUT /system: where the system receives webhooks; starts the verification when the URL changes.
     *
     * @param  list<int>  $contractVersions
     */
    public function present(string $webhookUrl, array $contractVersions = [Contract::VERSION]): SystemData
    {
        return SystemData::from($this->data($this->request('PUT', 'system', [
            'webhook_url' => $webhookUrl,
            'contract_versions' => $contractVersions,
        ])));
    }

    /**
     * GET /typologies: the active typologies.
     *
     * @return list<TypologyData>
     */
    public function typologies(): array
    {
        return array_map(TypologyData::from(...), $this->list($this->request('GET', 'typologies')));
    }

    /**
     * GET /event-types: the catalogue of messages, always fresh.
     *
     * @return list<EventTypeData>
     */
    public function eventTypes(): array
    {
        return array_map(EventTypeData::from(...), $this->list($this->request('GET', 'event-types')));
    }

    /**
     * PUT /members: the complete list of the system's members. Those left out become inactive.
     *
     * @param  list<MemberData>  $members
     * @return list<RegisteredMemberData>
     */
    public function syncMembers(array $members): array
    {
        if (count($members) > Contract::MAX_MEMBERS)
        {
            throw new PlatformRequestException(
                message: 'Troppi aderenti in una sola richiesta.',
                status: 422,
                errors: ['members' => ['Al massimo '.Contract::MAX_MEMBERS.' aderenti.']],
            );
        }

        $payload = array_map(static fn (MemberData $member): array => $member->toArray(), $members);

        foreach ($payload as $member)
        {
            MemberData::validate($member);
        }

        return array_map(
            RegisteredMemberData::from(...),
            $this->list($this->request('PUT', 'members', ['members' => $payload])),
        );
    }

    /**
     * GET /members: the listed members of the other systems.
     */
    public function searchMembers(?string $typology = null, ?string $search = null, ?int $perPage = null, ?string $cursor = null): MemberPage
    {
        $response = $this->request('GET', 'members', array_filter([
            'typology' => $typology,
            'search' => $search,
            'per_page' => $perPage,
            'cursor' => $cursor,
        ], static fn (mixed $value): bool => $value !== null));

        $nextCursor = $response->json('meta.next_cursor');
        $previousCursor = $response->json('meta.prev_cursor');

        return new MemberPage(
            members: array_map(ListedMemberData::from(...), $this->list($response)),
            nextCursor: is_string($nextCursor) ? $nextCursor : null,
            previousCursor: is_string($previousCursor) ? $previousCursor : null,
        );
    }

    /**
     * POST /events. Sending the same envelope again is safe: the platform answers with the event it already has.
     */
    public function send(EnvelopeData $envelope): AcceptedEventData
    {
        EnvelopeData::validate($envelope->toArray());

        if ($this->config->validatePayloads)
        {
            PayloadValidator::validate($envelope, $this->catalogue());
        }

        return AcceptedEventData::from($this->data($this->request('POST', 'events', $envelope->toWire())));
    }

    /**
     * The catalogue used by the local validation, cached for `catalog_ttl` seconds.
     *
     * @return list<EventTypeData>
     */
    private function catalogue(): array
    {
        $cached = $this->cache->get(self::CATALOGUE_CACHE_KEY);

        if (is_array($cached))
        {
            return array_values(array_map(EventTypeData::from(...), $cached));
        }

        $catalogue = $this->eventTypes();

        $this->cache->put(
            self::CATALOGUE_CACHE_KEY,
            array_map(static fn (EventTypeData $eventType): array => $eventType->toArray(), $catalogue),
            $this->config->catalogTtl,
        );

        return $catalogue;
    }

    /**
     * @param  array<string, mixed>  $body
     *
     * @throws PlatformNotConfiguredException
     * @throws PlatformRequestException
     */
    private function request(string $method, string $path, array $body = []): Response
    {
        $url = $this->config->apiUrl($path);

        $response = $this->pending()->withToken($this->accessToken())->send($method, $url, $this->options($method, $body));

        if ($response->status() === 401)
        {
            // The cached token may have expired early or belong to a rotated secret.
            $this->cache->forget($this->tokenCacheKey());

            $response = $this->pending()->withToken($this->accessToken())->send($method, $url, $this->options($method, $body));
        }

        if ($response->failed())
        {
            throw $this->exception($response);
        }

        return $response;
    }

    private function pending(): PendingRequest
    {
        return $this->http
            ->timeout($this->config->timeout)
            ->acceptJson()
            ->retry(
                times: $this->config->retries + 1,
                sleepMilliseconds: 200,
                when: static fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                throw: false,
            );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function options(string $method, array $body): array
    {
        return $method === 'GET' ? ['query' => $body] : ['json' => $body];
    }

    private function accessToken(): string
    {
        $cached = $this->cache->get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '')
        {
            return $cached;
        }

        $response = $this->http
            ->timeout($this->config->timeout)
            ->acceptJson()
            ->post($this->config->tokenUrl(), [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config->clientId,
                'client_secret' => $this->config->clientSecret,
                'scope' => Contract::SCOPE,
            ]);

        $token = $response->json('access_token');

        if ($response->failed() || !is_string($token) || $token === '')
        {
            throw new PlatformRequestException(
                message: 'Credenziali del portale non valide.',
                status: $response->status(),
            );
        }

        $expiresIn = $response->json('expires_in');

        $this->cache->put($this->tokenCacheKey(), $token, max(60, (is_int($expiresIn) ? $expiresIn : 3600) - 60));

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return 'platform.access-token.'.sha1((string) $this->config->clientId);
    }

    private function exception(Response $response): PlatformRequestException
    {
        $message = $response->json('message');
        $reason = $response->json('reason');
        $existing = $response->json('data');

        return new PlatformRequestException(
            message: is_string($message) ? $message : 'Richiesta al portale non riuscita.',
            status: $response->status(),
            errors: $this->errors($response->json('errors')),
            reason: is_string($reason) ? SystemStatus::tryFrom($reason) : null,
            existingEvent: $response->status() === 409 && is_array($existing) ? AcceptedEventData::from($existing) : null,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(mixed $errors): array
    {
        if (!is_array($errors))
        {
            return [];
        }

        $normalized = [];

        foreach ($errors as $key => $messages)
        {
            $normalized[(string) $key] = array_values(array_filter((array) $messages, is_string(...)));
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response): array
    {
        $data = $response->json('data');

        /** @var array<string, mixed> */
        return is_array($data) ? $data : [];
    }

    /**
     * @return list<mixed>
     */
    private function list(Response $response): array
    {
        $data = $response->json('data');

        return is_array($data) ? array_values($data) : [];
    }
}
