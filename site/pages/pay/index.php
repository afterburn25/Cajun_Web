<?php
/** Shareable invoice payment-request page. The bearer link grants view access to one request only; it never creates a portal session or records a payment. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/billing/payments.php';
payments_link_page(is_string($_GET['t'] ?? null) ? $_GET['t'] : '');
