<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Vps;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A concrete VPS configuration: compute, memory, storage, region and OS
 * image. All values are caller-supplied — the component invents no
 * real provider regions, images or sizes.
 */
final readonly class VpsSpec
{
    public function __construct(
        public int $vcpuCores,
        public int $ramGb,
        public int $diskGb,
        public string $region,
        public string $osImage,
    ) {
        if ($vcpuCores < 1 || $vcpuCores > 64) {
            throw new InvalidArgumentException('vCPU count must be within 1–64.');
        }
        if ($ramGb < 1 || $ramGb > 512) {
            throw new InvalidArgumentException('RAM must be within 1–512 GB.');
        }
        if ($diskGb < 1 || $diskGb > 8192) {
            throw new InvalidArgumentException('Disk must be within 1–8192 GB.');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $region)) {
            throw new InvalidArgumentException('Region must be a lowercase slug (e.g. us-east-1).');
        }
        if (trim($osImage) === '' || strlen($osImage) > 120) {
            throw new InvalidArgumentException('OS image needs a name of 1–120 characters.');
        }
    }
}
