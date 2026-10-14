<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
foreach (['ProviderCapability','ProviderCredential','TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderOperation','OperationResult','ConnectionCheck','RetryPolicy','LedgerClaim','OperationLedger','ProviderAdapter','ProviderGateway'] as $class) {
    require_once __DIR__.'/../../foundation/provider/'.$class.'.php';
}
require_once __DIR__.'/../../foundation/mail-transport/EndpointPolicy.php';
require_once __DIR__.'/../../foundation/mail-transport/ProbeFailure.php';
require_once __DIR__.'/../../foundation/mail-transport/EndpointResolver.php';
foreach (['TwentyIAdapter','ProbeOnlyLedger','FixedHttpTransport'] as $class) require_once __DIR__.'/../../foundation/twentyi/'.$class.'.php';
