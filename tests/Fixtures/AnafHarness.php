<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Tests\Fixtures;

use AtlasFlow\EFacturaRo\Anaf\AnafClient;
use AtlasFlow\EFacturaRo\Anaf\Environment;
use AtlasFlow\EFacturaRo\Support\FrozenClock;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/** A mock PSR-18 client wired into AnafClient, with helpers to queue MF's documented responses. */
final class AnafHarness
{
    public MockClient $http;

    public AnafClient $client;

    public Psr17Factory $factory;

    public function __construct(Environment $environment = Environment::TEST)
    {
        $this->http = new MockClient;
        $this->factory = new Psr17Factory;
        $this->client = new AnafClient($this->http, $this->factory, $this->factory, $environment, FrozenClock::at('2026-09-16 10:00:00'));
    }

    public function willAnswer(int $status, string $body, string $contentType = 'application/json', array $headers = []): self
    {
        $this->http->addResponse(new Response($status, ['Content-Type' => $contentType, ...$headers], $body));

        return $this;
    }

    public function willAnswerFixture(int $status, string $fixture): self
    {
        $type = str_ends_with($fixture, '.xml') ? 'application/xml' : 'application/json';

        return $this->willAnswer($status, (string) file_get_contents(__DIR__.'/../fixtures/anaf/'.$fixture), $type);
    }

    public function lastRequest(): RequestInterface
    {
        return $this->http->getLastRequest() ?: throw new \RuntimeException('No request was sent.');
    }

    /** @return array<string, string> */
    public function lastQuery(): array
    {
        parse_str($this->lastRequest()->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }
}
