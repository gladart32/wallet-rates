<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Security\SignatureBuilder;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class RateControllerTest extends WebTestCase
{
    private const API_KEY = 'mk_acme_dev_key_0001';
    private const API_SECRET = 'sk_acme_dev_secret_8a1b6f2c4e9d3a7b';
    private const DISABLED_API_KEY = 'mk_legacy_dev_key_0003';

    private KernelBrowser $client;
    private SignatureBuilder $signatureBuilder;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->signatureBuilder = new SignatureBuilder();
    }

    public function testListReturns401WithoutApiKey(): void
    {
        $this->client->request('GET', '/api/v1/rates');

        self::assertResponseStatusCodeSame(401);
        $body = $this->decodeJson();
        self::assertSame('unauthorized', $body['error']);
        self::assertStringContainsString('X-API-Key', $body['message']);
    }

    public function testListReturns401WithoutSignature(): void
    {
        $this->client->request('GET', '/api/v1/rates', server: [
            'HTTP_X_API_KEY' => self::API_KEY,
        ]);

        self::assertResponseStatusCodeSame(401);
        $body = $this->decodeJson();
        self::assertSame('unauthorized', $body['error']);
        self::assertStringContainsString('X-API-Signature', $body['message']);
    }

    public function testListReturns401WithInvalidApiKey(): void
    {
        $this->signedRequest('GET', '/api/v1/rates', [
            'HTTP_X_API_KEY' => 'invalid_key',
        ]);

        self::assertResponseStatusCodeSame(401);
        $body = $this->decodeJson();
        self::assertStringContainsString('Invalid', $body['message']);
    }

    public function testListReturns401WithInvalidSignature(): void
    {
        $this->client->request('GET', '/api/v1/rates', server: [
            'HTTP_X_API_KEY' => self::API_KEY,
            'HTTP_X_API_SIGNATURE' => str_repeat('0', 128),
        ]);

        self::assertResponseStatusCodeSame(401);
        $body = $this->decodeJson();
        self::assertSame('unauthorized', $body['error']);
        self::assertStringContainsString('signature', $body['message']);
    }

    public function testListReturns401ForDisabledMerchant(): void
    {
        $this->signedRequest('GET', '/api/v1/rates', [
            'HTTP_X_API_KEY' => self::DISABLED_API_KEY,
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testListReturnsActiveRates(): void
    {
        $this->signedRequest('GET', '/api/v1/rates');

        self::assertResponseStatusCodeSame(200);
        $body = $this->decodeJson();

        self::assertIsArray($body);
        self::assertNotEmpty($body);

        foreach ($body as $rate) {
            self::assertArrayHasKey('id', $rate);
            self::assertArrayHasKey('provider', $rate);
            self::assertArrayHasKey('currencyFrom', $rate);
            self::assertArrayHasKey('currencyTo', $rate);
            self::assertArrayHasKey('value', $rate);
            self::assertArrayHasKey('status', $rate);
            self::assertSame('active', $rate['status']);
        }
    }

    public function testGetOneReturnsActiveRateForMerchantBaseCurrency(): void
    {
        $this->signedRequest('GET', '/api/v1/rates/EUR');

        self::assertResponseStatusCodeSame(200);
        $body = $this->decodeJson();

        self::assertSame('USD', $body['currencyFrom']);
        self::assertSame('EUR', $body['currencyTo']);
        self::assertSame('active', $body['status']);
    }

    public function testGetOneNormalizesLowercaseCurrency(): void
    {
        $this->signedRequest('GET', '/api/v1/rates/eur');

        self::assertResponseStatusCodeSame(200);
        $body = $this->decodeJson();
        self::assertSame('EUR', $body['currencyTo']);
    }

    public function testGetOneReturns404ForUnknownPair(): void
    {
        $this->signedRequest('GET', '/api/v1/rates/ZZZ');

        self::assertResponseStatusCodeSame(404);
        $body = $this->decodeJson();
        self::assertSame('not_found', $body['error']);
        self::assertStringContainsString('USD -> ZZZ', $body['message']);
    }

    public function testDocsAreAccessibleWithoutAuth(): void
    {
        $this->client->request('GET', '/api/doc');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/doc.json');
        self::assertResponseIsSuccessful();
        $body = $this->decodeJson();
        self::assertSame('Rates API', $body['info']['title']);
        self::assertArrayHasKey('/api/v1/rates', $body['paths']);
        self::assertArrayHasKey('/api/v1/rates/{currency}', $body['paths']);
    }

    public function testOpenApiSpecContainsSecuritySchemes(): void
    {
        $this->client->request('GET', '/api/doc.json');
        $body = $this->decodeJson();

        self::assertArrayHasKey('ApiKey', $body['components']['securitySchemes']);
        self::assertSame('X-API-Key', $body['components']['securitySchemes']['ApiKey']['name']);
        self::assertSame('header', $body['components']['securitySchemes']['ApiKey']['in']);

        self::assertArrayHasKey('ApiSignature', $body['components']['securitySchemes']);
        self::assertSame('X-API-Signature', $body['components']['securitySchemes']['ApiSignature']['name']);
    }

    public function testOpenApiSpecContainsRateResponseSchema(): void
    {
        $this->client->request('GET', '/api/doc.json');
        $body = $this->decodeJson();

        self::assertArrayHasKey('RateResponse', $body['components']['schemas']);
        $props = $body['components']['schemas']['RateResponse']['properties'];
        self::assertArrayHasKey('id', $props);
        self::assertArrayHasKey('provider', $props);
        self::assertArrayHasKey('currencyFrom', $props);
        self::assertArrayHasKey('currencyTo', $props);
        self::assertArrayHasKey('value', $props);
        self::assertArrayHasKey('status', $props);
    }

    private function signedRequest(string $method, string $uri, array $server = []): void
    {
        $server['HTTP_X_API_KEY'] = $server['HTTP_X_API_KEY'] ?? self::API_KEY;

        $path = $this->extractPath($uri);
        $query = parse_url($uri, PHP_URL_QUERY) ?? '';
        $request = Request::create($path.'.q', $method);
        $request->server->set('QUERY_STRING', $query);

        $canonical = $this->buildCanonicalForServerRequest($method, $path, $query, '');
        $signature = hash_hmac('sha512', $canonical, self::API_SECRET);

        $server['HTTP_X_API_SIGNATURE'] = $signature;
        $this->client->request($method, $uri, server: $server);
    }

    private function buildCanonicalForServerRequest(string $method, string $path, string $query, string $body): string
    {
        $tmp = Request::create('/_internal_'.$path.($query !== '' ? '?'.$query : ''), $method, server: [], content: $body);
        $pathInfo = $tmp->getPathInfo();
        $internalPrefix = '/_internal_';
        $realPath = str_starts_with($pathInfo, $internalPrefix)
            ? substr($pathInfo, strlen($internalPrefix))
            : $pathInfo;

        $request = Request::create($realPath.($query !== '' ? '?'.$query : ''), $method, server: [], content: $body);

        return $this->signatureBuilder->buildCanonical($request);
    }

    private function extractPath(string $uri): string
    {
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '';
        if ($path === '') {
            $path = $uri;
        }

        return $path;
    }

    private function decodeJson(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
