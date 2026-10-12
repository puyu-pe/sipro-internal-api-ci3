<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PuyuPe\SiproInternalApiCi3\Http\InternalApiDispatcher;
use PuyuPe\SiproInternalApiCi3\Nonce\DbNonceStore;
use PuyuPe\SiproInternalApiCi3\Support\Headers;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\{TenantProvisioningAdapterInterface, TenantLifecycleAdapterInterface, TenantCloneAdapterInterface, TenantImpersonationAdapterInterface};

class InternalApi extends CI_Controller
{
    public function _remap($segment1, $params = [])
    {
        $this->config->load('internal_api', true);
        $config = $this->config->item('internal_api', 'internal_api');
        $capabilities = [
            TenantProvisioningAdapterInterface::class => 'provisioning',
            TenantLifecycleAdapterInterface::class => 'lifecycle',
            TenantCloneAdapterInterface::class => 'clone',
            TenantImpersonationAdapterInterface::class => 'impersonation',
        ];
        $dispatcher = new InternalApiDispatcher(
            $config,
            function (string $interface) use ($config, $capabilities) {
                $factory = $config['adapters'][$capabilities[$interface]] ?? null;
                if (!is_callable($factory)) { throw new RuntimeException('Adapter factory is not configured.'); }
                return $factory($this);
            },
            function () use ($config) {
                return new DbNonceStore($this->load->database($config['hmac']['nonce']['connection'], true));
            },
            static function (Throwable $error): void { log_message('error', $error::class . ': ' . $error->getMessage()); }
        );
        $result = $dispatcher->dispatch(
            $this->input->method(true),
            (string) ($_SERVER['REQUEST_URI'] ?? ''),
            $this->input->raw_input_stream,
            Headers::fromGlobals()
        );
        foreach ($result->headers as $name => $value) { $this->output->set_header($name . ': ' . $value); }
        return $this->output->set_status_header($result->status)->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($result->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
