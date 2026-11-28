"""Guard the held exact-source mailbox read/unread acceptance workflow."""
from pathlib import Path
import json

ROOT=Path(__file__).resolve().parents[1]
WORKFLOW=ROOT/'.github/workflows/mail-flags-native.yml'

def require(value,message):
    if not value: raise AssertionError(message)

def validate(text):
    checks=[]
    def check(name,value):
        require(value,name);checks.append({'name':name,'passed':True})
    check('Workflow is one ordinary YAML document','\t' not in text and '\n---\n' not in text)
    check('Repository access is read-only',text.count('\npermissions:\n  contents: read\n')==1)
    check('Development cancellation cannot discard main evidence','group: mail-flags-native-${{ github.event.pull_request.number || github.ref }}' in text and "cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}" in text)
    check('Natural push and pull-request events cover every flag seam',text.count('branches: [main]')==2 and text.count("'tests/mail_flags_native_workflow_contract.py'")==2 and text.count("'.github/scripts/prepare_ubuntu_apt.py'")==2 and text.count("'.github/workflows/mail-flags-native.yml'")==2 and text.count("'foundation/mail-receiving/**'")==2 and text.count("'site/app/mail/flags.php'")==2 and text.count("'site/database/migrations/mail_flags_migrate.php'")==2 and text.count("'site/install/inventory.generated.php'")==2 and text.count("'tests/mail_message_cases.py'")==2)
    check('Manual redispatch cannot become acceptance evidence','workflow_dispatch:' not in text)
    immutable='${{ github.event.pull_request.head.sha || github.sha }}'
    check('Source checkout and admission use the immutable event head',text.count('ref: '+immutable)==1 and text.count('CWP_SOURCE_SHA: '+immutable)==1)
    check('Matrix checkout and environment use only admitted source',text.count('ref: ${{ needs.source.outputs.sha }}')==1 and text.count('CWP_SOURCE_SHA: ${{ needs.source.outputs.sha }}')==2)
    check('Both checkouts are pinned and discard credentials',text.count('actions/checkout@11d5960a326750d5838078e36cf38b85af677262')==2 and text.count('persist-credentials: false')==2)
    check('Only first attempts are admitted with run identity',text.count('CWP_RUN_ID: ${{ github.run_id }}')==2 and text.count('CWP_RUN_ATTEMPT: ${{ github.run_attempt }}')==2 and text.count('test "$CWP_RUN_ATTEMPT" = "1"')==2 and text.count('${{ github.run_id }}-attempt-${{ github.run_attempt }}')==2)
    check('Every hosted lane uses reviewed signed-source image admission and retains failure evidence before package networking',text.count('sudo python3 -I .github/scripts/prepare_ubuntu_apt.py prepare-ubuntu')==2 and text.count('prepare-ubuntu >')==2 and text.count('|| status=$?')>=2 and text.count('sudo test -f qa/ubuntu-apt-source-preflight.json')==2 and text.count('sudo mv qa/ubuntu-apt-source-preflight.json')==2 and text.count('if [ -f qa/ubuntu-apt-source.json ]; then sudo mv qa/ubuntu-apt-source.json')==2 and text.count('runner-image.log')==2 and text.index('prepare_ubuntu_apt.py prepare-ubuntu')<text.index('docker build --build-arg PHP_VERSION='))
    check('Source gate proves workflow, syntax, mirrors and exact portable counts','python3 tests/mail_flags_native_workflow_contract.py' in text and 'python3 -m py_compile tests/mail_inbox_cases.py tests/mail_inbox_http_server.py tests/mail_message_cases.py' in text and "for file in FlagSession.php FlagWriter.php ReadProfile.php ReceivingFailure.php" in text and 'flags="-n -d extension=ctype"' in text and text.count('php $flags tests/mail-receiving/flag_test.php')==1 and text.count('php $flags tests/database_schema_registry_policy.php')==1 and "('flags',48),('schema-registry',410)" in text and "assert report['passed'] is True and len(report['checks'])==count" in text)
    check('Supported PHP and database matrix is complete',"php: ['8.2', '8.4']" in text and 'database: [mariadb, mysql]' in text and "image: ${{ matrix.database == 'mariadb' && 'mariadb:10.11' || 'mysql:8.4' }}" in text)
    check('Disposable official PHP includes PDO MySQL and mbstring','FROM php:${PHP_VERSION}-cli' in text and 'docker-php-ext-install pdo_mysql mbstring' in text)
    check('MySQL trigger fixtures receive only their required local setting',"SET GLOBAL log_bin_trust_function_creators=1" in text and '${{ job.services.database.id }}' in text and 'mysql-global.stdout.log' in text and 'mysql-global.stderr.log' in text)
    check('Synthetic database readiness is bounded and retained','for($i=0;$i<60;$i++)' in text and 'usleep(500000)' in text and 'readiness.stdout.log' in text and 'readiness.stderr.log' in text)
    check('Native lane uses synthetic private storage and one bounded suite','chmod 700 test-runtime/mail-flags-private' in text and 'realpath("test-runtime/mail-flags-private")' in text and text.count('\n            php tests/mail_flags_native.php\n')==1 and 'example.net' not in text and 'example.invalid' not in text)
    check('Native report requires exact complete feature assertions',"len(report['checks'])==45" in text and 'Incompatible later sibling is preflighted before any flag table creation' in text and 'Failed sibling preflight creates no earlier request table' in text and 'Transport unconfirmed returns a finite failure' in text and 'Flag request and audit storage contain no passwords or remote exception text' in text and "required <= names" in text)
    check('Failure output is retained before status enforcement','stdout.log' in text and 'stderr.log' in text and 'status.txt' in text and text.index("printf 'status=%s")<text.index('test "$status" -eq 0',text.index("printf 'status=%s")))
    check('Source and every matrix cell retain unconditional exact identity',text.count('actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02')==2 and text.count('if: always()')==3 and text.count('if-no-files-found: error')==2 and 'git archive --format=tar.gz HEAD' in text and 'tree=$(git rev-parse HEAD^{tree})' in text and '> "$cell/identity.txt"' in text and text.index('> "$cell/identity.txt"')<text.index('docker build --build-arg PHP_VERSION='))
    check('No softened failure, credential secret, live mail, or remote-operation channel exists',all(token not in text for token in ('continue-on-error','set +e','|| true','secrets.','curl ','ssh ','workflow_run:','smtp_host','imap_host')))
    check('Final checkpoint requires source and every native cell','needs: [source, native]' in text)
    return checks

def self_test(text):
    checks=validate(text)
    mutations=(
        ('reduced PHP matrix',"php: ['8.2', '8.4']","php: ['8.4']"),
        ('reduced database matrix','database: [mariadb, mysql]','database: [mysql]'),
        ('moving source','ref: ${{ github.event.pull_request.head.sha || github.sha }}','ref: main'),
        ('writable repository','contents: read','contents: write'),
        ('rerun acceptance','test "$CWP_RUN_ATTEMPT" = "1"','test -n "$CWP_RUN_ATTEMPT"'),
        ('lost runner guard','sudo python3 -I .github/scripts/prepare_ubuntu_apt.py prepare-ubuntu','true # runner admission omitted'),
        ('lost runner failure receipt','sudo test -f qa/ubuntu-apt-source-preflight.json','true # preflight receipt omitted'),
        ('lost PDO MySQL','docker-php-ext-install pdo_mysql mbstring','docker-php-ext-install mbstring'),
        ('lost explicit no-INI ctype','flags="-n -d extension=ctype"','flags="-n"'),
        ('weakened native count',"len(report['checks'])==45","len(report['checks'])>=40"),
        ('lost preflight assertion','Incompatible later sibling is preflighted before any flag table creation','Unrelated assertion'),
        ('softened failure','git diff --exit-code','git diff --exit-code || true'),
        ('lost evidence','if: always()','if: success()'),
    )
    for name,old,new in mutations:
        require(old in text,'Mutation fixture disappeared: '+name)
        try: validate(text.replace(old,new,1))
        except AssertionError: checks.append({'name':'Regression rejected: '+name,'passed':True})
        else: raise AssertionError('Regression accepted: '+name)
    return {'passed':True,'scope':__doc__+' No hosted, browser, package, provider, customer-message, or live-operation result.','checks':checks}

if __name__=='__main__':
    print(json.dumps(self_test(WORKFLOW.read_text()),indent=2))
