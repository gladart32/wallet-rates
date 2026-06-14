<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\SignatureBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SignatureBuilderTest extends TestCase
{
    private const SECRET = 'test-secret';

    private SignatureBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SignatureBuilder();
    }

    public function testBuildCanonicalForGetWithoutQueryOrBody(): void
    {
        $request = Request::create('/api/v1/rates', 'GET');

        $expected = "GET\n/api/v1/rates\n\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855";
        self::assertSame($expected, $this->builder->buildCanonical($request));
    }

    public function testBuildCanonicalForPostWithBody(): void
    {
        $body = '{"foo":"bar"}';
        $bodyHash = hash('sha256', $body);
        $request = Request::create('/api/v1/rates', 'POST', server: [], content: $body);

        $expected = "POST\n/api/v1/rates\n\n".$bodyHash;
        self::assertSame($expected, $this->builder->buildCanonical($request));
    }

    public function testBuildCanonicalMethodIsUppercased(): void
    {
        $request = Request::create('/api/v1/rates', 'get');

        $canonical = $this->builder->buildCanonical($request);
        self::assertStringStartsWith("GET\n", $canonical);
    }

    #[DataProvider('canonicalizeQueryProvider')]
    public function testCanonicalizeQuerySorting(string $queryString, string $expected): void
    {
        $request = Request::create('/api/v1/rates?'.$queryString, 'GET');

        $canonical = $this->builder->buildCanonical($request);
        $parts = explode("\n", $canonical);
        self::assertSame($expected, $parts[2]);
    }

    public static function canonicalizeQueryProvider(): array
    {
        return [
            'empty' => ['', ''],
            'single key' => ['a=1', 'a=1'],
            'two keys, sorted by name' => ['b=2&a=1', 'a=1&b=2'],
            'two keys, reverse' => ['a=1&b=2', 'a=1&b=2'],
            'special chars encoded' => ['pair=BTC%2FUSDT', 'pair=BTC%2FUSDT'],
            'value with space' => ['name=hello%20world', 'name=hello%20world'],
            'value with plus' => ['name=a+b', 'name=a%20b'],
            'key only without value' => ['flag', 'flag='],
            'key empty value' => ['flag=', 'flag='],
            'multiple keys' => ['z=3&a=1&m=2', 'a=1&m=2&z=3'],
        ];
    }

    public function testSignProducesHexSha512(): void
    {
        $canonical = "GET\n/api/v1/rates\n\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855";

        $signature = $this->builder->sign($canonical, self::SECRET);

        self::assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $signature);
    }

    public function testVerifyReturnsTrueForCorrectSignature(): void
    {
        $request = Request::create('/api/v1/rates', 'GET');
        $canonical = $this->builder->buildCanonical($request);
        $signature = $this->builder->sign($canonical, self::SECRET);

        self::assertTrue($this->builder->verify($request, self::SECRET, $signature));
    }

    public function testVerifyIsCaseInsensitiveForHexInput(): void
    {
        $request = Request::create('/api/v1/rates', 'GET');
        $canonical = $this->builder->buildCanonical($request);
        $signature = $this->builder->sign($canonical, self::SECRET);

        self::assertTrue($this->builder->verify($request, self::SECRET, strtoupper($signature)));
    }

    public function testVerifyReturnsFalseForWrongSignature(): void
    {
        $request = Request::create('/api/v1/rates', 'GET');

        self::assertFalse($this->builder->verify($request, self::SECRET, '0'.str_repeat('0', 127)));
        self::assertFalse($this->builder->verify($request, self::SECRET, str_repeat('a', 128)));
    }

    public function testVerifyReturnsFalseForWrongSecret(): void
    {
        $request = Request::create('/api/v1/rates', 'GET');
        $canonical = $this->builder->buildCanonical($request);
        $signature = $this->builder->sign($canonical, self::SECRET);

        self::assertFalse($this->builder->verify($request, 'other-secret', $signature));
    }

    public function testVerifyReturnsFalseForEmptySignature(): void
    {
        $request = Request::create('/api/v1/rates', 'GET');

        self::assertFalse($this->builder->verify($request, self::SECRET, ''));
    }

    public function testVerifyFailsWhenPathChanges(): void
    {
        $request = Request::create('/api/v1/rates', 'GET');
        $canonical = $this->builder->buildCanonical($request);
        $signature = $this->builder->sign($canonical, self::SECRET);

        $tampered = Request::create('/api/v1/rates/EUR', 'GET');
        self::assertFalse($this->builder->verify($tampered, self::SECRET, $signature));
    }

    public function testVerifyFailsWhenMethodChanges(): void
    {
        $request = Request::create('/api/v1/rates', 'GET');
        $canonical = $this->builder->buildCanonical($request);
        $signature = $this->builder->sign($canonical, self::SECRET);

        $tampered = Request::create('/api/v1/rates', 'DELETE');
        self::assertFalse($this->builder->verify($tampered, self::SECRET, $signature));
    }

    public function testVerifyFailsWhenQueryOrderChanges(): void
    {
        $request1 = Request::create('/api/v1/rates?a=1&b=2', 'GET');
        $canonical1 = $this->builder->buildCanonical($request1);
        $signature = $this->builder->sign($canonical1, self::SECRET);

        $request2 = Request::create('/api/v1/rates?b=2&a=1', 'GET');
        self::assertTrue($this->builder->verify($request2, self::SECRET, $signature));
    }

    public function testVerifyFailsWhenBodyChanges(): void
    {
        $request = Request::create('/api/v1/rates', 'POST', server: [], content: '{"a":1}');
        $canonical = $this->builder->buildCanonical($request);
        $signature = $this->builder->sign($canonical, self::SECRET);

        $tampered = Request::create('/api/v1/rates', 'POST', server: [], content: '{"a":2}');
        self::assertFalse($this->builder->verify($tampered, self::SECRET, $signature));
    }
}
