<?php

namespace App\Services;

use App\Contracts\PunchOutGatewayInterface;
use App\Gateways\AbetaGateway;
use App\Gateways\OciGateway;
use App\Gateways\CxmlGateway;
use Exception;

class PunchOutGatewayManager
{
    /**
     * Resolves the gateway based on the given provider name.
     */
    public function resolve(string $providerName): PunchOutGatewayInterface
    {
        return match (strtolower($providerName)) {
            'abeta' => new AbetaGateway(),
            'oci' => new OciGateway(),
            'cxml' => new CxmlGateway(),
            default => throw new Exception("Gateway provider [{$providerName}] not supported."),
        };
    }
}
