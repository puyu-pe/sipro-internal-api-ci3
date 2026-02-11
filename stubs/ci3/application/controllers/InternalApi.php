<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PuyuPe\SiproInternalApiCi3\Http\InternalApiDispatcher;
use PuyuPe\SiproInternalApiCi3\Nonce\DbNonceStore;
use PuyuPe\SiproInternalApiCi3\Security\VerifyHmac;
use PuyuPe\SiproInternalApiCi3\Support\Headers;
use PuyuPe\SiproInternalApiCore\Contracts\TenantAdapterInterface;

class InternalApi extends CI_Controller
{
    /**
     * CI3 single entrypoint for /internal/v1/... requests.
     *
     * @param string $segment1
     * @param array<int,string> $params
     */
    public function _remap($segment1, $params = [])
    {
        $method = strtoupper($this->input->method(TRUE) ?: ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST') {
            return $this->sendJson(405, [
                'ok' => false,
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Only POST is allowed.',
            ]);
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '/';
        $rawBody = file_get_contents('php://input') ?: '';
        $headers = Headers::fromGlobals();

        $config = $this->loadInternalApiConfig();

        $resolveSecretByKeyId = $this->buildSecretResolver($config);

        $nonceStore = null;
        if (!empty($config['nonce_enabled'])) {
            $this->load->database();
            $nonceStore = new DbNonceStore($this->db);
        }

        $verify = new VerifyHmac();
        $verifyError = $verify->verifyOrError(
            $method,
            $path,
            $rawBody,
            $headers,
            $resolveSecretByKeyId,
            $nonceStore
        );

        if ($verifyError !== null) {
            return $this->sendJson($verifyError['status'], $verifyError['body']);
        }

        $adapter = $this->resolveTenantAdapter($config);

        $dispatcher = new InternalApiDispatcher();
        $result = $dispatcher->dispatch($method, $path, $rawBody, $headers, $adapter);

        return $this->sendJson($result['status'], $result['body']);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadInternalApiConfig(): array
    {
        $config = $this->config->item('internal_api');
        if (is_array($config)) {
            return $config;
        }

        $this->config->load('internal_api', TRUE);
        $config = $this->config->item('internal_api', 'internal_api');

        return is_array($config) ? $config : [];
    }

    /**
     * @param array<string,mixed> $config
     * @return callable(string):?string
     */
    private function buildSecretResolver(array $config): callable
    {
        if (isset($config['resolve_secret_by_key_id']) && is_callable($config['resolve_secret_by_key_id'])) {
            return $config['resolve_secret_by_key_id'];
        }

        $keys = isset($config['keys']) && is_array($config['keys']) ? $config['keys'] : [];

        return static function (string $keyId) use ($keys): ?string {
            $value = $keys[$keyId] ?? null;
            return is_string($value) ? $value : null;
        };
    }

    /**
     * @param array<string,mixed> $config
     */
    private function resolveTenantAdapter(array $config): TenantAdapterInterface
    {
        if (isset($config['adapter']) && $config['adapter'] instanceof TenantAdapterInterface) {
            return $config['adapter'];
        }

        if (isset($config['adapter_factory']) && is_callable($config['adapter_factory'])) {
            $adapter = $config['adapter_factory']($this);
            if ($adapter instanceof TenantAdapterInterface) {
                return $adapter;
            }
        }

        $adapterClass = $config['adapter_class'] ?? 'ApplicationTenantAdapter';

        if (is_string($adapterClass) && class_exists($adapterClass)) {
            $adapter = new $adapterClass($this);
            if ($adapter instanceof TenantAdapterInterface) {
                return $adapter;
            }
        }

        show_error('Tenant adapter must implement TenantAdapterInterface.', 500);
        exit;
    }

    /**
     * @param array<string,mixed> $body
     */
    private function sendJson(int $status, array $body)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
