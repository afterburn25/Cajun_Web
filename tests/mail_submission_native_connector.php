<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailTransport {
    function dns_get_record(string $host,int $type):array|false {
        if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||$host!=='submission-fixture.example.net'||$type!==(DNS_A|DNS_AAAA|DNS_CNAME))throw new \RuntimeException('Isolated fixture forbids external DNS');
        ($GLOBALS['submission_fixture_dns'])();return [['type'=>'A','ip'=>'8.8.8.8']];
    }
}
namespace { require_once __DIR__.'/mail_submission_socket_connector.php'; }
