<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/registered_nameservers.php';

function registered_nameservers_admin_action(string $action): never {
    provider_settings_manager();
    $password = posted('current_password', 200);
    if ($action === 'provider_inventory_nameservers_prepare') {
        registered_nameservers_prepare($password);
        flash('Nameserver observations are ready.');
    } elseif ($action === 'provider_inventory_nameservers_refresh') {
        $number = static function(string $name): int {
            $value = posted($name, 10);
            if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $value) || (int)$value > 4294967295) {
                throw new InvalidArgumentException('Reload the nameserver form.');
            }
            return (int)$value;
        };
        $result = registered_nameservers_refresh(posted('link_id', 32), $number('link_revision'),
            $number('profile_revision'), posted('refresh_id', 32), $password);
        $complete = $result['state'] === 'complete';
        flash($complete ? 'The observed nameservers were saved.' : 'Nameservers are unavailable from the latest refresh.',
            $complete ? 'success' : 'error');
    } else {
        throw new InvalidArgumentException('Unknown nameserver action.');
    }
    redirect(admin_url('provider-settings').'#registered-nameservers');
}
