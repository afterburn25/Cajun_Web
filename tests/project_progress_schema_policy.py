#!/usr/bin/env python3
"""Static fail-closed contract for project-progress storage registration."""
from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
schema = (root / 'site/database/schema/project_progress_schema.php').read_text(encoding='utf-8')
validator = (root / 'site/database/schema/project_progress_validate_schema.php').read_text(encoding='utf-8')
migration = (root / 'site/database/migrations/project_progress_migrate.php').read_text(encoding='utf-8')
registry = (root / 'site/app/settings/database_schemas.php').read_text(encoding='utf-8')

checks = []

def check(name: str, condition: bool) -> None:
    checks.append({'name': name, 'passed': bool(condition)})
    if not condition:
        raise AssertionError(name)

try:
    tables = ('studio_progress_events', 'studio_progress_requests', 'studio_progress_heads')
    check('Schema declares each exact progress table once',
          all(schema.count("'" + table + "'=>") == 1 for table in tables))
    check('Schema uses restrictive same-product relationships',
          schema.count('ON DELETE RESTRICT ON UPDATE RESTRICT') == 6)
    check('Event, request and head identities use exact binary collations',
          schema.count('CHAR(32) CHARACTER SET ascii COLLATE ascii_bin') == 1
          and schema.count('CHAR(64) CHARACTER SET ascii COLLATE ascii_bin') == 1
          and schema.count('LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin') == 1)
    check('Schema retains revision, client, staff and head indexes',
          all(token in schema for token in (
              'UNIQUE KEY event_job(id,job_id)',
              'UNIQUE KEY job_revision(job_id,revision)',
              'INDEX job_client(job_id,client_id)',
              'INDEX client_updates(client_id,created_at,id)',
              'INDEX staff_id(staff_id)', 'UNIQUE KEY head_event(event_id)',
              'INDEX event_job(event_id,id)')))
    check('Composite relationships bind each event and head to the same project',
          all(token in schema for token in (
              'FOREIGN KEY (job_id,client_id)',
              'FOREIGN KEY (event_id,id)'))
          and "[['job_id','client_id'],'studio_jobs',['id','client_id']]" in validator
          and "[['event_id','id'],'studio_progress_events',['id','job_id']]" in validator)
    check('Validator binds exact columns, indexes and current database relationships',
          all(token in validator for token in (
              'REFERENCED_TABLE_SCHEMA', "query('SELECT DATABASE()')->fetchColumn()",
              'Incompatible project progress columns',
              'Incompatible project progress indexes',
              'Incompatible project progress relationships')))
    check('Migration refuses support and requires the current active owner',
          all(token in migration for token in (
              'support_current()', "staff_require('staff.manage')",
              "($actor['role']??'')!=='owner'", '!admin_session_matches($actor)')))
    check('Established missing storage refuses restoration loss',
          "if($version==='1'&&in_array(false,$existing,true))" in migration
          and 'Restore the original database before continuing.' in migration)
    check('Blank markers never adopt populated progress storage',
          migration.count('Unrecognized project progress exists. No data was adopted.') == 2)
    check('Activation revalidates owner identity, auth version and marker under transaction',
          all(token in migration for token in (
              'staff_transaction(function()use($actor,$version,$schema)',
              "hash_equals((string)$current['auth_version'],(string)$actor['auth_version'])",
              "setting('project_progress_schema_version')!==$version")))
    check('Activation retains metadata locks and revalidates every exact schema before marking ready',
          "query('SELECT id FROM '.table('studio_jobs').' ORDER BY id LIMIT 1 FOR UPDATE')" in migration
          and "query('SELECT id FROM '.table($name).' ORDER BY id LIMIT 1 FOR UPDATE')" in migration
          and 'project_progress_validate_schema($name);' in migration
          and 'project_progress_validate_workspace_identity();' in migration)
    check('Setup audit is content-free and names no client or project data',
          "'project.progress.setup'" in migration
          and 'Additive project progress storage verified; existing workspace records preserved' in migration)
    check('Central registry exposes one optional customer-experience migration',
          registry.count("'project_progress_schema_version'=>'1'") == 1
          and registry.count("'project_progress_schema_version'=>['depends'=>['staff_schema_version','studio_schema_version']") == 1
          and "'function'=>'project_progress_migrate'" in registry
          and "'function'=>'project_progress_migrate','arguments'=>'none'" in registry)
except Exception as error:
    print(json.dumps({'passed': False, 'count': len(checks), 'checks': checks,
                      'failure': str(error)}, indent=2))
    raise SystemExit(1)

print(json.dumps({'passed': True, 'count': len(checks), 'checks': checks,
                  'scope': 'Static schema, migration and centralized-registry policy only.'}, indent=2))
