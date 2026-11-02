<?php
/** Run the unmodified existing ledger suite against a separate synthetic CI prefix. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$runtime=__DIR__.'/../test-runtime';$private=$runtime.'/design-legacy-ledger-private';
@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$fixture=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-ledger-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'ledgerci_']];
if(file_put_contents($runtime.'/config-fixture.php',"<?php\nif(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);\nreturn ".var_export($fixture,true).";\n")===false)throw new RuntimeException('Could not prepare isolated fixture.');
require __DIR__.'/ledger_native.php';
