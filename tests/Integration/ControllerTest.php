<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PuyuPe\SiproInternalApiCore\Conformance\{ConformanceTests, RecordingAdapter};
use PuyuPe\SiproInternalApiCore\Errors\InternalApiException;
use PuyuPe\SiproInternalApiCore\Http\InternalApi;
use PuyuPe\SiproInternalApiCore\Http\Response\ApiResponse;
use PuyuPe\SiproInternalApiCore\Security\Hmac\HmacSigner;

$contracts = dirname((new \ReflectionClass(InternalApi::class))->getFileName(), 3) . '/resources/contracts/v2/';
require_once $contracts . 'RecordingAdapter.php';
require_once $contracts . 'ConformanceTests.php';
require_once $contracts . 'CloneEndpointTests.php';

final class ControllerTest extends TestCase
{
    use ConformanceTests;
    use \PuyuPe\SiproInternalApiCore\Conformance\CloneEndpointTests;
    private RecordingAdapter $adapter;
    private mixed $server;
    private string $address;
    private string $state;
    private array $overrides = [];

    protected function setUp(): void
    {
        $this->adapter = new RecordingAdapter();
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->state = tempnam(sys_get_temp_dir(), 'sipro-ci3-');
        $this->server = proc_open([PHP_BINARY, '-S', $this->address, __DIR__ . '/../Fixtures/router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $this->state . '.log', 'a'], 2 => ['file', $this->state . '.log', 'a']], $pipes, null,
            ['SIPRO_TEST_STATE' => $this->state] + getenv());
        fclose($pipes[0]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @stream_socket_client('tcp://' . $this->address, $errno, $error, 0.05);
            if ($socket) { fclose($socket); return; }
            usleep(10000);
        }
        self::fail('CI3 server failed: ' . file_get_contents($this->state . '.log'));
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) { proc_terminate($this->server); proc_close($this->server); }
        foreach ([$this->state, $this->state . '.result', $this->state . '.log'] as $file) {
            if (is_file($file)) { unlink($file); }
        }
    }

    protected function recordingAdapter(): RecordingAdapter { return $this->adapter; }

    protected function configureCloneFixture(string $directory, array $databases): void
    {
        $this->overrides['clone_fixture'] = compact('directory', 'databases');
    }

    protected function executeRequest(string $method, string $path, string $body, array $headers): ApiResponse
    {
        $failure = $this->adapter->failure;
        file_put_contents($this->state, json_encode(['config' => $this->overrides, 'failure' => $failure instanceof InternalApiException
            ? ['code' => $failure->error()->code->value, 'message' => $failure->error()->message, 'details' => $failure->error()->details]
            : ($failure ? ['message' => $failure->getMessage()] : null)], JSON_THROW_ON_ERROR));
        $lines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) { $lines[] = $name . ': ' . $value; }
        $context = stream_context_create(['http' => ['method' => $method, 'content' => $body, 'header' => implode("\r\n", $lines), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
        $output = file_get_contents('http://' . $this->address . $path, false, $context);
        self::assertIsString($output, file_get_contents($this->state . '.log'));
        preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $status);
        $responseHeaders = [];
        foreach ($http_response_header as $header) {
            if (str_starts_with(strtolower($header), 'allow:')) { $responseHeaders['Allow'] = trim(substr($header, 6)); }
        }
        $decoded = json_decode($output, true);
        self::assertIsArray($decoded, $output . file_get_contents($this->state . '.log'));
        $calls = (int) file_get_contents($this->state . '.result');
        array_push($this->adapter->calls, ...array_fill(0, $calls, ['http request']));
        return new ApiResponse((int) $status[1], $decoded, $responseHeaders);
    }

    public function testConfiguredSkewAndDisabledIntegration(): void
    {
        $this->overrides = ['hmac' => ['allowed_clock_skew_seconds' => 10]];
        $path = '/internal/v1/tenants/tenant:warn';
        $body = '{"resolveKey":"tenant","projectCode":"PROJ"}';
        $headers = (new HmacSigner())->buildSignedHeaders('POST', $path, $body, 'test', 'secret', (string) (time() - 20));
        self::assertSame('REQUEST_EXPIRED', $this->executeRequest('POST', $path, $body, $headers)->body['error']['code']);
        $this->overrides = ['enabled' => false];
        self::assertSame(404, $this->executeRequest('POST', $path, $body, [])->status);
    }

    public function testMissingAdapterAndConfiguredDuration(): void
    {
        $this->overrides = ['adapters' => ['lifecycle' => null]];
        $response = $this->signedRequest('/internal/v1/tenants/tenant:warn', ['resolveKey' => 'tenant', 'projectCode' => 'PROJ']);
        self::assertSame(500, $response->status);
        self::assertSame('ADAPTER_NOT_CONFIGURED', $response->body['error']['code']);
        $this->overrides = ['impersonation' => ['duration' => ['default_minutes' => 10, 'min_minutes' => 5, 'max_minutes' => 20]]];
        $response = $this->signedRequest('/internal/v1/tenants/tenant:impersonate', ['resolveKey' => 'tenant', 'projectCode' => 'PROJ', 'targetUserId' => 42]);
        self::assertSame(200, $response->status);
        self::assertSame(10, $response->body['effectiveDurationMinutes']);
    }

    public function testReplayProtectionThroughConfiguredMasterConnection(): void
    {
        if (!getenv('SIPRO_TEST_DB_HOST')) { self::markTestSkipped('Disposable database required.'); }
        $pdo = new \PDO('mysql:host=' . getenv('SIPRO_TEST_DB_HOST') . ';dbname=sipro_test', 'root', '');
        $schema = file_get_contents(__DIR__ . '/../../stubs/ci3/internal_api_nonces.sql');
        $pdo->exec(str_replace('CREATE TABLE ', 'CREATE TABLE IF NOT EXISTS ', $schema));
        $this->overrides = ['hmac' => ['nonce' => ['enabled' => true]]];
        $path = '/internal/v1/tenants/tenant:warn';
        $body = '{"resolveKey":"tenant","projectCode":"PROJ"}';
        $headers = (new HmacSigner())->buildSignedHeaders('POST', $path, $body, 'test', 'secret');
        self::assertSame(401, $this->executeRequest('POST', $path, $body . ' ', $headers)->status);
        self::assertSame(200, $this->executeRequest('POST', $path, $body, $headers)->status);
        self::assertSame('NONCE_REPLAY', $this->executeRequest('POST', $path, $body, $headers)->body['error']['code']);
        self::assertCount(1, $this->adapter->calls);
    }
}
