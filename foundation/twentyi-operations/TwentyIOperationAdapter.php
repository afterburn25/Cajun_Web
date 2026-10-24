<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{ConnectionCheck,HttpTransport,OperationResult,ProviderAdapter,ProviderCapability,ProviderCredential,ProviderOperation,TransportException};
use CajunWebPro\Foundation\TwentyI\TwentyIAdapter;
use InvalidArgumentException;

/**
 * The write-path adapter for the documented package-lifecycle catalog.
 *
 * supports() is true only for shape-complete registered operations;
 * capabilities() reports those as supported and catalogued-but-shapeless
 * ones as unverified. execute() builds the bounded request, attaches the
 * credential at this boundary only, sends it once through the injected
 * transport, and classifies conservatively — ambiguous post-send
 * outcomes are UNCERTAIN, never silently retried.
 *
 * Execution authority is not here: ProviderGateway claims the shared
 * durable ledger, and the application layer requires an active owner
 * grant for the exact scope before perform() is ever called.
 */
final class TwentyIOperationAdapter implements ProviderAdapter
{
    private const MAX_KEY_BYTES = 4096;

    private TwentyIOperationRegister $register;
    private TwentyIRequestBuilder $builder;
    private TwentyIAdapter $probe;
    private bool $mailboxConfigured;
    private bool $templateConfigured;

    public function __construct(?callable $mailboxPassword = null, ?callable $templateContent = null)
    {
        $this->register = new TwentyIOperationRegister();
        $this->builder = new TwentyIRequestBuilder($this->register, $mailboxPassword, $templateContent);
        $this->probe = new TwentyIAdapter();
        $this->mailboxConfigured = $mailboxPassword !== null;
        $this->templateConfigured = $templateContent !== null;
    }

    public function id(): string { return $this->probe->id(); }
    public function label(): string { return $this->probe->label(); }

    /**
     * @return list<ProviderCapability>
     */
    public function capabilities(): array
    {
        $capabilities = [];
        foreach ($this->register->names() as $name) {
            $spec = $this->register->get($name);
            if ($spec === null) continue;
            if ($name === PasswordResetTemplateChange::OPERATION && !$this->templateConfigured) {
                $capabilities[] = ProviderCapability::unverified($name, 'Requires dedicated template review and a protected content resolver.');
                continue;
            }
            if ($name === MailboxCreate::OPERATION && !$this->mailboxConfigured) {
                $capabilities[] = ProviderCapability::unverified($name, 'Requires dedicated resource authorization and a protected password resolver.');
                continue;
            }
            $capabilities[] = $spec->shapeComplete
                ? ProviderCapability::supported($name, 'Documented write; requires an active owner grant and durable ledger claim.', false)
                : ProviderCapability::unverified($name, 'Endpoint documented without body fields; request shape unverified.');
        }
        return $capabilities;
    }

    public function supports(string $operation): bool
    {
        if ($operation === PasswordResetTemplateChange::OPERATION && !$this->templateConfigured) return false;
        if ($operation === MailboxCreate::OPERATION && !$this->mailboxConfigured) return false;
        $spec = $this->register->get($operation);
        return $spec !== null && $spec->shapeComplete;
    }

    public function probe(HttpTransport $transport, #[\SensitiveParameter] ProviderCredential $credential): ConnectionCheck
    {
        return $this->probe->probe($transport, $credential);
    }

    public function execute(HttpTransport $transport, #[\SensitiveParameter] ProviderCredential $credential, ProviderOperation $operation): OperationResult
    {
        if (!$this->supports($operation->name)) {
            return OperationResult::unsupported($operation->name);
        }
        try {
            $request = $this->builder->build($operation);
        } catch (InvalidArgumentException) {
            return OperationResult::failed($operation->name, 'The request shape was refused.', 'invalid_request');
        }

        $secret = $credential->secret();
        try {
            if ($secret === '' || strlen($secret) > self::MAX_KEY_BYTES || preg_match('/[\x00-\x1f\x7f]/', $secret)) {
                return OperationResult::failed($operation->name, 'The stored credential is unusable.', 'invalid_credential');
            }
            // The fixed transport requires this exact header order:
            // Authorization first, then the documented JSON headers.
            $request = new \CajunWebPro\Foundation\Provider\TransportRequest(
                $request->method, $request->url,
                [
                    'Authorization' => 'Bearer ' . base64_encode($secret),
                    'Accept' => $request->headers['Accept'],
                    'Content-Type' => $request->headers['Content-Type'],
                ],
                $request->body, $request->timeoutSeconds
            );

            try {
                $response = $transport->send($request);
            } catch (InvalidArgumentException) {
                // A transport-side request refusal happens provably before
                // any send — failed, never uncertain.
                return OperationResult::failed($operation->name, 'The request was refused before send.', 'refused');
            } catch (TransportException $e) {
                return TwentyIResultClassifier::fromTransportFailure($operation->name, $e);
            }
            if ($operation->name === DomainRenewal::OPERATION) return DomainRenewal::result($response, $secret);
            if ($operation->name === PasswordResetTemplateChange::OPERATION) return PasswordResetTemplateChange::result($response, $secret);
            return $operation->name === MailboxCreate::OPERATION
                ? MailboxCreate::result($operation, $request, $response)
                : TwentyIResultClassifier::fromResponse($operation->name, $response);
        } finally {
            unset($secret);
        }
    }
}
