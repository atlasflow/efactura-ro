<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Tests\Fixtures;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * The smallest PSR-18 client that can reach ANAF: PHP streams, no
 * dependency. Only the live suites use it; consumers bring their own.
 */
final class PsrHttp
{
    public static function factory(): Psr17Factory
    {
        return new Psr17Factory;
    }

    public static function client(): ClientInterface
    {
        return new class implements ClientInterface
        {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $headers = [];

                foreach ($request->getHeaders() as $name => $values) {
                    $headers[] = $name.': '.implode(', ', $values);
                }

                $context = stream_context_create(['http' => [
                    'method' => $request->getMethod(),
                    'header' => implode("\r\n", $headers),
                    'content' => (string) $request->getBody(),
                    'timeout' => 60,
                    'ignore_errors' => true,
                ]]);

                $body = @file_get_contents((string) $request->getUri(), false, $context);

                if ($body === false) {
                    throw new class('Cannot reach '.$request->getUri()->getHost()) extends RuntimeException implements ClientExceptionInterface {};
                }

                $status = 0;
                $responseHeaders = [];

                foreach ($http_response_header ?? [] as $line) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                        $status = (int) $m[1];
                        $responseHeaders = [];
                    } elseif (str_contains($line, ':')) {
                        [$name, $value] = explode(':', $line, 2);
                        $responseHeaders[trim($name)][] = trim($value);
                    }
                }

                return new Response($status ?: 500, $responseHeaders, $body);
            }
        };
    }
}
