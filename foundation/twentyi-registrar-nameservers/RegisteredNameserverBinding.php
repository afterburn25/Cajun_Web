<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIRegistrarNameservers;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/../twentyi-inventory/InventoryDomain.php';
require_once __DIR__.'/../twentyi-domain-contracts/DomainContractBinding.php';
use CajunWebPro\Foundation\TwentyIDomainContracts\DomainContractBinding;

/** Reuse exact registrar-link provenance validation; a binding is not current owner authority. */
final readonly class RegisteredNameserverBinding
{
    public array $data;
    public string $json;
    public string $sha256;
    public string $scope;
    public function __construct(array $input)
    {
        $registered=new DomainContractBinding($input);
        $this->data=$registered->data;$this->json=$registered->json;$this->sha256=$registered->sha256;
        $this->scope='twenty-i|'.$this->data['profile_id'].'|registrar.nameservers.read|'.$this->sha256;
    }
}
