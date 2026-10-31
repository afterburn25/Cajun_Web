<?php
/** Render the real selected workspace without replacing the fixture's $checks array. */
declare(strict_types=1);
function cwp_test_provider_workspace(array $query): string {
    $saved=$_GET;$_GET=$query;ob_start();
    try{require CWP_CODE_ROOT.'/admin/views/providers.php';return (string)ob_get_contents();}
    finally{ob_end_clean();$_GET=$saved;}
}
