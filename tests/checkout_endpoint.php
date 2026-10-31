<?php
/** Explicit client/server port distinction for disposable checkout fixtures only. */
declare(strict_types=1);
function cwp_test_checkout_endpoint(int $clientPort,int $reportedPort): bool {
    $mapped=getenv('CWP_TEST_CHECKOUT_DOCKER_PORT');
    if($clientPort!==43319)return false;
    if($mapped===false||$mapped==='')return $reportedPort===43319;
    return $mapped==='3306'&&getenv('CWP_TEST_HOST')==='127.0.0.1'
        &&getenv('CWP_TEST_DATABASE')==='cwp_checkout_test'&&$reportedPort===3306;
}
