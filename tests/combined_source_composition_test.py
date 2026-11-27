"""Exercise the composition boundary by mutating real disposable source files."""
from pathlib import Path
import argparse
import ast
import copy
import hashlib
import importlib.util
import io
import json
import os
import py_compile
import re
import subprocess
import sys
import tempfile
import types

import combined_source_composition as proof

ROOT = Path(__file__).resolve().parents[1]


def run(root=ROOT):
    checks = []

    def passed(name):
        checks.append({'name': name, 'passed': True})

    def refuses(name, operation):
        try:
            operation()
        except AssertionError:
            passed(name)
        else:
            raise AssertionError('Accepted unreviewed composition: ' + name)

    refuses('Refuses an incomplete provider artifact validation batch',
            lambda: proof.provider_browser_artifact_validations(root, ()))
    retained_failure = []
    proof._extend_provider_artifact_rows(
        retained_failure,
        [[{'name': 'Synthetic exact historical failure', 'passed': False}]])
    assert retained_failure == [
        {'name': 'Synthetic exact historical failure', 'passed': False}]
    passed('Provider artifact batching retains exact named historical failures')
    body = b'exact immutable batch bytes\x00\n'
    object_id = hashlib.sha1(
        b'blob ' + str(len(body)).encode('ascii') + b'\0' + body).hexdigest().encode('ascii')
    expression = '1' * 40 + ':tests/example.bin'

    def batch_process(raw):
        return types.SimpleNamespace(stdin=io.BytesIO(), stdout=io.BytesIO(raw))

    packet = object_id + b' blob ' + str(len(body)).encode('ascii') + b'\n' + body + b'\n'
    process = batch_process(packet)
    assert proof._read_immutable_blob(process, expression) == body
    assert process.stdin.getvalue() == expression.encode('ascii') + b'\n'
    passed('Immutable blob batch verifies exact binary bytes and request framing')
    for label, raw in (
        ('missing object', object_id + b' missing\n'),
        ('non-blob object', object_id + b' tree 1\nx\n'),
        ('noncanonical size', object_id + b' blob 01\nx\n'),
        ('truncated body', object_id + b' blob 3\nab'),
        ('wrong delimiter', object_id + b' blob 3\nabc!'),
        ('corrupt identity', object_id + b' blob 3\nabc\n'),
        ('overlong header', b'a' * 200 + b'\n'),
    ):
        refuses('Immutable blob batch refuses ' + label,
                lambda raw=raw: proof._read_immutable_blob(batch_process(raw), expression))
    refuses('Immutable blob batch refuses request framing injection',
            lambda: proof._read_immutable_blob(batch_process(packet), expression + '\ncontents deadbeef'))
    caller_refused = False
    try:
        with proof.immutable_blob_batch(root):
            refuses('Immutable blob batch refuses a nested reader',
                    lambda: proof.immutable_blob_batch(root).__enter__())
            raise RuntimeError('Synthetic caller refusal')
    except RuntimeError:
        caller_refused = True
    assert caller_refused and not proof._BLOB_BATCHES
    passed('Immutable blob batch reaps its child after a failing caller')
    with tempfile.TemporaryDirectory(prefix='cwp-projection-cache-negative-') as folder:
        fixture_root = Path(folder)
        fixture_path = fixture_root / 'exact.txt'
        fixture_path.write_bytes(b'initial exact bytes')
        second_root = fixture_root / 'second'
        second_root.mkdir()
        (second_root / 'exact.txt').write_bytes(b'initial exact bytes')
        calls = []

        def project_cache_probe(root, path, current):
            calls.append((str(Path(root).resolve()), path, current))
            return proof.ordinary(root, path)

        def project_cache_nested_probe(root, path, current):
            return proof.project_cache_probe(root, path, current)

        proof.project_cache_probe = project_cache_probe
        proof.project_cache_nested_probe = project_cache_nested_probe
        try:
            with proof.verification_projection_cache(fixture_root):
                assert proof.project_cache_nested_probe(
                    fixture_root, 'exact.txt', b'input') == b'initial exact bytes'
                assert proof.project_cache_nested_probe(
                    fixture_root, 'exact.txt', b'input') == b'initial exact bytes'
                assert len(calls) == 1
                fixture_path.write_bytes(b'changed during proof')
                assert proof.project_cache_nested_probe(
                    fixture_root, 'exact.txt', b'input') == b'changed during proof'
                assert len(calls) == 2
                assert proof.project_cache_nested_probe(
                    second_root, 'exact.txt', b'input') == b'initial exact bytes'
                assert len(calls) == 3
        finally:
            del proof.project_cache_nested_probe
            del proof.project_cache_probe
        original_ordinary = proof.ordinary
        reads = []
        stable = []

        def racing_ordinary(root, path):
            if Path(root) == fixture_root and path == 'exact.txt':
                reads.append(path)
                if stable:
                    return b'changed during proof'
                return (b'changed during proof' if len(reads) == 1
                        else b'raced projection bytes')
            return original_ordinary(root, path)

        proof.project_cache_probe = project_cache_probe
        proof.project_cache_nested_probe = project_cache_nested_probe
        proof.ordinary = racing_ordinary
        try:
            with proof.verification_projection_cache(fixture_root):
                refuses('Bound projection cache refuses a mutable-source race',
                        lambda: proof.project_cache_nested_probe(
                            fixture_root, 'exact.txt', b'raced input'))
                stable.append(True)
                assert proof.project_cache_nested_probe(
                    fixture_root, 'exact.txt', b'raced input') == b'changed during proof'
                assert len(calls) == 5
        finally:
            proof.ordinary = original_ordinary
            del proof.project_cache_nested_probe
            del proof.project_cache_probe
        assert reads == ['exact.txt'] * 6
        assert not proof._ACTIVE_ROOT_KEYS

        original_limit = proof._PROJECTION_CACHE_LIMIT
        leaf_calls = []

        def project_cache_leaf(root, path, current):
            leaf_calls.append(current)
            return current

        def project_cache_many(root, path, current):
            for value in (b'a', b'b', b'c', b'a'):
                proof.project_cache_leaf(root, path, value)
            return current

        def project_cache_touch(root, path, current):
            proof.project_cache_leaf(root, path, b'a')
            return current

        def project_cache_refuse(root, path, current):
            for value in (b'a', b'b', b'c'):
                proof.project_cache_leaf(root, path, value)
            raise AssertionError('synthetic bounded transaction refusal')

        def project_cache_interleaved(root, path, current):
            proof.project_cache_leaf(root, path, b'a')
            proof.project_cache_leaf(root, path, b'b')
            return current

        def project_cache_full_interleaved(root, path, current):
            proof.project_cache_leaf(root, path, b'a')
            proof.project_cache_leaf(root, path, b'x')
            return current

        def project_cache_raise(root, path, current):
            raise AssertionError('synthetic nested refusal')

        def project_cache_caught_touch(root, path, current):
            proof.project_cache_leaf(root, path, b'a')
            try:
                proof.project_cache_raise(root, path, b'raise')
            except AssertionError:
                pass
            return current

        def project_cache_cross_root_open(root, path, current):
            proof.project_cache_leaf(root, path, b'a')
            try:
                proof.project_cache_leaf(second_root, 'missing.txt', b'missing')
            except AssertionError:
                pass
            return current

        def project_cache_nested_pending_refuse(root, path, current):
            proof.project_cache_leaf(root, path, b'c')
            proof.project_cache_leaf(root, path, b'd')
            raise AssertionError('synthetic nested pending refusal')

        def project_cache_caught_pending(root, path, current):
            proof.project_cache_leaf(root, path, b'a')
            proof.project_cache_leaf(root, path, b'b')
            try:
                proof.project_cache_nested_pending_refuse(root, path, b'raise')
            except AssertionError:
                pass
            return current

        proof.project_cache_leaf = project_cache_leaf
        proof.project_cache_many = project_cache_many
        proof.project_cache_touch = project_cache_touch
        proof.project_cache_refuse = project_cache_refuse
        proof.project_cache_interleaved = project_cache_interleaved
        proof.project_cache_full_interleaved = project_cache_full_interleaved
        proof.project_cache_raise = project_cache_raise
        proof.project_cache_caught_touch = project_cache_caught_touch
        proof.project_cache_cross_root_open = project_cache_cross_root_open
        proof.project_cache_nested_pending_refuse = project_cache_nested_pending_refuse
        proof.project_cache_caught_pending = project_cache_caught_pending
        proof._PROJECTION_CACHE_LIMIT = 2
        try:
            with proof.verification_projection_cache(fixture_root):
                assert proof.project_cache_many(
                    fixture_root, 'exact.txt', b'outer') == b'outer'
            assert leaf_calls == [b'a', b'b', b'c', b'a']
            assert proof._LAST_PROJECTION_CACHE_MAX == 2

            leaf_calls.clear()
            with proof.verification_projection_cache(fixture_root):
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'a') == b'a'
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'b') == b'b'
                assert proof.project_cache_touch(
                    fixture_root, 'exact.txt', b'c') == b'c'
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'a') == b'a'
            assert leaf_calls == [b'a', b'b']
            assert proof._LAST_PROJECTION_CACHE_MAX == 2

            leaf_calls.clear()
            with proof.verification_projection_cache(fixture_root):
                refuses('Bounded transaction never publishes a refused outer chain',
                        lambda: proof.project_cache_refuse(
                            fixture_root, 'exact.txt', b'refuse'))
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'c') == b'c'
            assert leaf_calls == [b'a', b'b', b'c', b'c']
            assert proof._LAST_PROJECTION_CACHE_MAX == 2

            leaf_calls.clear()
            with proof.verification_projection_cache(fixture_root):
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'b') == b'b'
                assert proof.project_cache_interleaved(
                    fixture_root, 'exact.txt', b'c') == b'c'
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'b') == b'b'
            assert leaf_calls == [b'b', b'a']
            assert proof._LAST_PROJECTION_CACHE_MAX == 2

            leaf_calls.clear()
            with proof.verification_projection_cache(fixture_root):
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'a') == b'a'
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'b') == b'b'
                assert proof.project_cache_caught_touch(
                    fixture_root, 'exact.txt', b'c') == b'c'
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'a') == b'a'
            assert leaf_calls == [b'a', b'b', b'a']

            leaf_calls.clear()
            with proof.verification_projection_cache(fixture_root):
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'a') == b'a'
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'b') == b'b'
                assert proof.project_cache_cross_root_open(
                    fixture_root, 'exact.txt', b'c') == b'c'
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'a') == b'a'
            assert leaf_calls == [b'a', b'b', b'a']

            leaf_calls.clear()
            with proof.verification_projection_cache(fixture_root):
                assert proof.project_cache_caught_pending(
                    fixture_root, 'exact.txt', b'outer') == b'outer'
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'b') == b'b'
            assert leaf_calls == [b'a', b'b', b'c', b'd', b'b']
            assert proof._LAST_PROJECTION_CACHE_MAX == 2

            proof._PROJECTION_CACHE_LIMIT = 3
            leaf_calls.clear()
            with proof.verification_projection_cache(fixture_root):
                for value in (b'x', b'y', b'z'):
                    assert proof.project_cache_leaf(
                        fixture_root, 'exact.txt', value) == value
                assert proof.project_cache_full_interleaved(
                    fixture_root, 'exact.txt', b'c') == b'c'
                assert proof.project_cache_leaf(
                    fixture_root, 'exact.txt', b'x') == b'x'
            assert leaf_calls == [b'x', b'y', b'z', b'a']
            assert proof._LAST_PROJECTION_CACHE_MAX == 3
        finally:
            proof._PROJECTION_CACHE_LIMIT = original_limit
            del proof.project_cache_caught_pending
            del proof.project_cache_nested_pending_refuse
            del proof.project_cache_cross_root_open
            del proof.project_cache_caught_touch
            del proof.project_cache_raise
            del proof.project_cache_full_interleaved
            del proof.project_cache_interleaved
            del proof.project_cache_refuse
            del proof.project_cache_touch
            del proof.project_cache_many
            del proof.project_cache_leaf
        assert not proof._ACTIVE_ROOT_KEYS
        passed('Caught nested refusal resets the bounded transaction overlay')
        passed('Transactional projection staging and committed recency retain the strict LRU bound')
    passed('Bound projection cache preserves fresh reads, invalidation and root isolation')
    proof_drift = (proof.RECEIPT, proof.VERIFIER, proof.REGRESSION)
    assert proof.parse_tracked_source_drift(
        b'\0'.join(path.encode('utf-8') for path in proof_drift) + b'\0') == proof_drift
    for label, raw in (
        ('missing NUL terminator', proof.VERIFIER.encode('utf-8')),
        ('empty path', b'\0'),
        ('duplicate path', proof.VERIFIER.encode('utf-8') + b'\0'
         + proof.VERIFIER.encode('utf-8') + b'\0'),
        ('noncanonical path', b'tests//combined_source_composition.py\0'),
        ('parent traversal', b'../combined_source_composition.py\0'),
        ('non-UTF-8 path', b'tests/\xff.py\0'),
    ):
        refuses('Tracked-source preflight refuses ' + label,
                lambda raw=raw: proof.parse_tracked_source_drift(raw))
    passed('Tracked-source preflight parses only canonical NUL-delimited paths')
    original_component_match = proof.current_component_source_matches
    original_union_index_check = proof.require_component_union_index_unchanged
    union_events = []

    def observed_component_match(*args, **kwargs):
        union_events.append('component')
        return original_component_match(*args, **kwargs)

    def observed_union_index_check(*args, **kwargs):
        union_events.append('index')
        return original_union_index_check(*args, **kwargs)

    proof.current_component_source_matches = observed_component_match
    proof.require_component_union_index_unchanged = observed_union_index_check
    try:
        proof.verify_current(root)
    finally:
        proof.current_component_source_matches = original_component_match
        proof.require_component_union_index_unchanged = original_union_index_check
    assert (union_events.count('component') > 0
            and union_events.count('index') == 3
            and union_events[0] == 'index'
            and union_events[-2:] == ['index', 'index'])
    passed('Actual current combined checkout brackets component traversal with the authenticated index')
    assert (proof.notification_acceptance_checkout_head(root)
            == proof.COMPONENT_FAST_PATH_BASE)
    passed('Authenticated component proof projects notification history to exact verified main')
    assert (proof.git(root, 'rev-parse', proof.COMPONENT_FAST_PATH_BASE + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_BASE_TREE
            and proof.git(root, 'rev-parse', proof.COMPONENT_FAST_PATH_SOURCE + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_SOURCE_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_PREDECESSOR_PROOF + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_PREDECESSOR_PROOF_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_CORRECTION_SOURCE + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_CORRECTION_SOURCE_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_REJECTED_PROOF + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_REJECTED_PROOF_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_HANDOFF_SOURCE + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_HANDOFF_SOURCE_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_PREFLIGHT_REJECTED_PROOF + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_PREFLIGHT_REJECTED_PROOF_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_PREFLIGHT_CORRECTION_SOURCE + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_PREFLIGHT_CORRECTION_SOURCE_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_RECOVERY_REJECTED_PROOF + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_RECOVERY_REJECTED_PROOF_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_RECOVERY_CORRECTION_SOURCE + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_RECOVERY_CORRECTION_SOURCE_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_INVENTORY_REJECTED_PROOF + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_INVENTORY_REJECTED_PROOF_TREE
            and proof.git(root, 'rev-parse',
                          proof.COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE + '^{tree}')
            .decode().strip() == proof.COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE_TREE)
    for attribute, label in (
        ('COMPONENT_FAST_PATH_BASE', 'base commit'),
        ('COMPONENT_FAST_PATH_BASE_TREE', 'base tree'),
        ('COMPONENT_FAST_PATH_SOURCE', 'implementation commit'),
        ('COMPONENT_FAST_PATH_SOURCE_TREE', 'implementation tree'),
        ('COMPONENT_FAST_PATH_PREDECESSOR_PROOF', 'predecessor proof commit'),
        ('COMPONENT_FAST_PATH_PREDECESSOR_PROOF_TREE', 'predecessor proof tree'),
        ('COMPONENT_FAST_PATH_CORRECTION_SOURCE', 'review correction commit'),
        ('COMPONENT_FAST_PATH_CORRECTION_SOURCE_TREE', 'review correction tree'),
        ('COMPONENT_FAST_PATH_REJECTED_PROOF', 'rejected proof commit'),
        ('COMPONENT_FAST_PATH_REJECTED_PROOF_TREE', 'rejected proof tree'),
        ('COMPONENT_FAST_PATH_HANDOFF_SOURCE', 'handoff correction commit'),
        ('COMPONENT_FAST_PATH_HANDOFF_SOURCE_TREE', 'handoff correction tree'),
        ('COMPONENT_FAST_PATH_PREFLIGHT_REJECTED_PROOF', 'preflight-rejected proof commit'),
        ('COMPONENT_FAST_PATH_PREFLIGHT_REJECTED_PROOF_TREE', 'preflight-rejected proof tree'),
        ('COMPONENT_FAST_PATH_PREFLIGHT_CORRECTION_SOURCE', 'preflight correction commit'),
        ('COMPONENT_FAST_PATH_PREFLIGHT_CORRECTION_SOURCE_TREE', 'preflight correction tree'),
        ('COMPONENT_FAST_PATH_RECOVERY_REJECTED_PROOF', 'recovery-rejected proof commit'),
        ('COMPONENT_FAST_PATH_RECOVERY_REJECTED_PROOF_TREE', 'recovery-rejected proof tree'),
        ('COMPONENT_FAST_PATH_RECOVERY_CORRECTION_SOURCE', 'recovery correction commit'),
        ('COMPONENT_FAST_PATH_RECOVERY_CORRECTION_SOURCE_TREE', 'recovery correction tree'),
        ('COMPONENT_FAST_PATH_INVENTORY_REJECTED_PROOF', 'inventory-rejected proof commit'),
        ('COMPONENT_FAST_PATH_INVENTORY_REJECTED_PROOF_TREE', 'inventory-rejected proof tree'),
        ('COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE', 'inventory correction commit'),
        ('COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE_TREE', 'inventory correction tree'),
    ):
        original = getattr(proof, attribute)
        try:
            setattr(proof, attribute, '0' * 40)
            refuses('Refuses substituted component fast-path ' + label,
                    lambda: proof.validate_component_fast_path_source(root))
        finally:
            setattr(proof, attribute, original)
    original_paths = proof.COMPONENT_FAST_PATH_SOURCE_PATHS
    try:
        proof.COMPONENT_FAST_PATH_SOURCE_PATHS = frozenset(
            set(original_paths) | {proof.RECEIPT})
        refuses('Refuses expanded component fast-path implementation path set',
                lambda: proof.validate_component_fast_path_source(root))
    finally:
        proof.COMPONENT_FAST_PATH_SOURCE_PATHS = original_paths
    original_hashes = proof.COMPONENT_FAST_PATH_SOURCE_SHA256
    try:
        proof.COMPONENT_FAST_PATH_SOURCE_SHA256 = {
            **original_hashes,
            proof.REGRESSION: '0' * 64,
        }
        refuses('Refuses substituted component fast-path implementation digest',
                lambda: proof.validate_component_fast_path_source(root))
    finally:
        proof.COMPONENT_FAST_PATH_SOURCE_SHA256 = original_hashes
    for paths_attribute, label in (
        ('COMPONENT_FAST_PATH_PREDECESSOR_PROOF_PATHS', 'predecessor proof'),
        ('COMPONENT_FAST_PATH_CORRECTION_PATHS', 'review correction'),
        ('COMPONENT_FAST_PATH_REJECTED_PROOF_PATHS', 'rejected proof'),
        ('COMPONENT_FAST_PATH_HANDOFF_SOURCE_PATHS', 'handoff correction'),
        ('COMPONENT_FAST_PATH_PREFLIGHT_REJECTED_PROOF_PATHS',
         'preflight-rejected proof'),
        ('COMPONENT_FAST_PATH_PREFLIGHT_CORRECTION_PATHS', 'preflight correction'),
        ('COMPONENT_FAST_PATH_RECOVERY_REJECTED_PROOF_PATHS',
         'recovery-rejected proof'),
        ('COMPONENT_FAST_PATH_RECOVERY_CORRECTION_PATHS', 'recovery correction'),
        ('COMPONENT_FAST_PATH_INVENTORY_REJECTED_PROOF_PATHS',
         'inventory-rejected proof'),
        ('COMPONENT_FAST_PATH_INVENTORY_CORRECTION_PATHS', 'inventory correction'),
        ('COMPONENT_FAST_PATH_PROOF_PATHS', 'final proof'),
    ):
        original_paths = getattr(proof, paths_attribute)
        try:
            setattr(proof, paths_attribute,
                    frozenset(set(original_paths) | {'unexpected.txt'}))
            refuses('Refuses expanded component fast-path ' + label + ' path set',
                    lambda: proof.validate_component_fast_path_source(root))
        finally:
            setattr(proof, paths_attribute, original_paths)
    for hashes_attribute, label in (
        ('COMPONENT_FAST_PATH_PREDECESSOR_PROOF_SHA256', 'predecessor proof'),
        ('COMPONENT_FAST_PATH_CORRECTION_SHA256', 'review correction'),
        ('COMPONENT_FAST_PATH_REJECTED_PROOF_SHA256', 'rejected proof'),
        ('COMPONENT_FAST_PATH_HANDOFF_SOURCE_SHA256', 'handoff correction'),
        ('COMPONENT_FAST_PATH_PREFLIGHT_REJECTED_PROOF_SHA256',
         'preflight-rejected proof'),
        ('COMPONENT_FAST_PATH_PREFLIGHT_CORRECTION_SHA256', 'preflight correction'),
        ('COMPONENT_FAST_PATH_RECOVERY_REJECTED_PROOF_SHA256',
         'recovery-rejected proof'),
        ('COMPONENT_FAST_PATH_RECOVERY_CORRECTION_SHA256', 'recovery correction'),
        ('COMPONENT_FAST_PATH_INVENTORY_REJECTED_PROOF_SHA256',
         'inventory-rejected proof'),
        ('COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SHA256', 'inventory correction'),
    ):
        original_hashes = getattr(proof, hashes_attribute)
        target = next(iter(original_hashes))
        try:
            setattr(proof, hashes_attribute,
                    {**original_hashes, target: '0' * 64})
            refuses('Refuses substituted component fast-path ' + label + ' digest',
                    lambda: proof.validate_component_fast_path_source(root))
        finally:
            setattr(proof, hashes_attribute, original_hashes)
    original_recovery_paths = proof.COMPONENT_FAST_PATH_RECOVERY_PATHS
    try:
        proof.COMPONENT_FAST_PATH_RECOVERY_PATHS = frozenset()
        refuses('Recovery successor refuses missing component fast-path handoff admission',
                lambda: proof.validate_pr391_recovery_successor(root))
        proof.COMPONENT_FAST_PATH_RECOVERY_PATHS = (
            original_recovery_paths | {'unexpected.txt'})
        refuses('Recovery successor refuses expanded component fast-path handoff admission',
                lambda: proof.validate_pr391_recovery_successor(root))
    finally:
        proof.COMPONENT_FAST_PATH_RECOVERY_PATHS = original_recovery_paths
    original_additions = proof.COMPONENT_FAST_PATH_ADDITIONS
    allowed_paths = proof.component_union_allowed_paths(root, {})
    assert proof.COMPONENT_FAST_PATH_HANDOFF in allowed_paths
    try:
        proof.COMPONENT_FAST_PATH_ADDITIONS = frozenset()
        refuses('Composition path inventory refuses missing fast-path handoff admission',
                lambda: proof.require(
                    proof.component_union_allowed_paths(root, {}) == allowed_paths,
                    'Component fast-path handoff admission is missing'))
        proof.COMPONENT_FAST_PATH_ADDITIONS = original_additions | {'unexpected.txt'}
        refuses('Composition path inventory refuses expanded fast-path handoff admission',
                lambda: proof.require(
                    proof.component_union_allowed_paths(root, {}) == allowed_paths,
                    'Component fast-path handoff admission expanded'))
    finally:
        proof.COMPONENT_FAST_PATH_ADDITIONS = original_additions
    direct_head = 'a' * 40
    direct_tree = 'b' * 40
    assert proof.validate_component_fast_path_checkout_history(
        direct_head, [proof.COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE], direct_tree,
        direct_head, [proof.COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE], direct_tree
    ) == direct_head
    assert proof.validate_component_fast_path_checkout_history(
        'c' * 40, [proof.COMPONENT_FAST_PATH_BASE, direct_head], direct_tree,
        direct_head, [proof.COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE], direct_tree
    ) == direct_head
    passed('Component fast path admits direct proof and exact tree-identical merge')
    refuses('Component fast-path proof refuses a non-direct parent',
            lambda: proof.validate_component_fast_path_checkout_history(
                direct_head, [proof.COMPONENT_FAST_PATH_BASE], direct_tree,
                direct_head, [proof.COMPONENT_FAST_PATH_BASE], direct_tree))
    refuses('Component fast-path proof refuses an extra merge parent',
            lambda: proof.validate_component_fast_path_checkout_history(
                'c' * 40,
                [proof.COMPONENT_FAST_PATH_BASE, direct_head, 'd' * 40],
                direct_tree, direct_head,
                [proof.COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE], direct_tree))
    refuses('Component fast-path proof refuses a changed merge base',
            lambda: proof.validate_component_fast_path_checkout_history(
                'c' * 40, ['d' * 40, direct_head], direct_tree,
                direct_head, [proof.COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE], direct_tree))
    refuses('Component fast-path proof refuses a non-direct branch head',
            lambda: proof.validate_component_fast_path_checkout_history(
                'c' * 40, [proof.COMPONENT_FAST_PATH_BASE, direct_head],
                direct_tree, direct_head,
                [proof.COMPONENT_FAST_PATH_BASE], direct_tree))
    refuses('Component fast-path proof refuses a changed merge tree',
            lambda: proof.validate_component_fast_path_checkout_history(
                'c' * 40, [proof.COMPONENT_FAST_PATH_BASE, direct_head],
                'd' * 40, direct_head,
                [proof.COMPONENT_FAST_PATH_INVENTORY_CORRECTION_SOURCE], direct_tree))
    original_actual_ordinary = proof.actual_ordinary

    def altered_component_fast_path_handoff(root_value, path):
        value = original_actual_ordinary(root_value, path)
        if path == proof.COMPONENT_FAST_PATH_HANDOFF:
            return value + b'\nUNREVIEWED FINAL HANDOFF CONTENT\n'
        return value

    proof.actual_ordinary = altered_component_fast_path_handoff
    try:
        refuses('Refuses arbitrary final handoff content',
                lambda: proof.validate_component_fast_path_source(root))
    finally:
        proof.actual_ordinary = original_actual_ordinary
    assert (proof.git(root, 'rev-parse', proof.NOTIFICATION_ACCEPTANCE_BASE + '^{tree}')
            .decode().strip() == proof.NOTIFICATION_ACCEPTANCE_BASE_TREE
            and proof.git(root, 'rev-parse', proof.NOTIFICATION_ACCEPTANCE_SOURCE + '^{tree}')
            .decode().strip() == proof.NOTIFICATION_ACCEPTANCE_SOURCE_TREE)
    with proof.notification_acceptance_projection(root):
        for path in proof.NOTIFICATION_ACCEPTANCE_MODIFIED:
            assert (proof.ordinary(root, path)
                    == proof.git(root, 'cat-file', 'blob',
                                 proof.NOTIFICATION_ACCEPTANCE_BASE + ':' + path))
    passed('Notification acceptance projection restores both exact verified-main paths')
    for attribute, label in (
        ('NOTIFICATION_ACCEPTANCE_BASE_TREE', 'base tree'),
        ('NOTIFICATION_ACCEPTANCE_MERGE', 'merge commit'),
        ('NOTIFICATION_ACCEPTANCE_MERGE_TREE', 'merge tree'),
        ('NOTIFICATION_ACCEPTANCE_BYPASS_FIX', 'bypass-fix commit'),
        ('NOTIFICATION_ACCEPTANCE_BYPASS_FIX_TREE', 'bypass-fix tree'),
        ('NOTIFICATION_ACCEPTANCE_STEP_BINDING', 'step-binding commit'),
        ('NOTIFICATION_ACCEPTANCE_STEP_BINDING_TREE', 'step-binding tree'),
        ('NOTIFICATION_ACCEPTANCE_SOURCE', 'source commit'),
        ('NOTIFICATION_ACCEPTANCE_SOURCE_TREE', 'source tree'),
        ('NOTIFICATION_ACCEPTANCE_REJECTED_PROOF', 'rejected proof commit'),
        ('NOTIFICATION_ACCEPTANCE_REJECTED_PROOF_TREE', 'rejected proof tree'),
        ('NOTIFICATION_ACCEPTANCE_HOSTED_REJECTED_PROOF',
         'hosted-rejected proof commit'),
        ('NOTIFICATION_ACCEPTANCE_HOSTED_REJECTED_PROOF_TREE',
         'hosted-rejected proof tree'),
    ):
        original = getattr(proof, attribute)
        try:
            setattr(proof, attribute, '0' * len(original))
            refuses('Refuses substituted notification acceptance ' + label,
                    lambda: proof.validate_notification_acceptance_source(root))
        finally:
            setattr(proof, attribute, original)
    direct_head = 'f' * 40
    direct_tree = 'd' * 40
    assert proof.validate_notification_acceptance_checkout_history(
        direct_head, [proof.NOTIFICATION_ACCEPTANCE_HOSTED_REJECTED_PROOF],
        direct_tree, direct_head,
        [proof.NOTIFICATION_ACCEPTANCE_HOSTED_REJECTED_PROOF], direct_tree
    ) == direct_head
    assert proof.validate_notification_acceptance_checkout_history(
        'c' * 40, [proof.NOTIFICATION_ACCEPTANCE_BASE, direct_head],
        direct_tree, direct_head,
        [proof.NOTIFICATION_ACCEPTANCE_HOSTED_REJECTED_PROOF], direct_tree
    ) == direct_head
    passed('Notification acceptance admits direct correction and exact tree-identical PR merge')
    refuses('Notification acceptance proof refuses a non-direct parent',
            lambda: proof.validate_notification_acceptance_checkout_history(
                direct_head, [proof.NOTIFICATION_ACCEPTANCE_REJECTED_PROOF],
                direct_tree, direct_head,
                [proof.NOTIFICATION_ACCEPTANCE_REJECTED_PROOF], direct_tree))
    refuses('Notification acceptance proof refuses an extra merge parent',
            lambda: proof.validate_notification_acceptance_checkout_history(
                'c' * 40,
                [proof.NOTIFICATION_ACCEPTANCE_BASE, direct_head, 'e' * 40],
                direct_tree, direct_head,
                [proof.NOTIFICATION_ACCEPTANCE_HOSTED_REJECTED_PROOF], direct_tree))
    refuses('Notification acceptance proof refuses a changed PR base parent',
            lambda: proof.validate_notification_acceptance_checkout_history(
                'c' * 40, ['e' * 40, direct_head], direct_tree,
                direct_head, [proof.NOTIFICATION_ACCEPTANCE_HOSTED_REJECTED_PROOF],
                direct_tree))
    refuses('Notification acceptance proof refuses a non-direct PR branch head',
            lambda: proof.validate_notification_acceptance_checkout_history(
                'c' * 40, [proof.NOTIFICATION_ACCEPTANCE_BASE, direct_head],
                direct_tree, direct_head,
                [proof.NOTIFICATION_ACCEPTANCE_REJECTED_PROOF], direct_tree))
    refuses('Notification acceptance proof refuses a changed PR merge tree',
            lambda: proof.validate_notification_acceptance_checkout_history(
                'c' * 40, [proof.NOTIFICATION_ACCEPTANCE_BASE, direct_head],
                'b' * 40, direct_head,
                [proof.NOTIFICATION_ACCEPTANCE_HOSTED_REJECTED_PROOF], direct_tree))
    assert (proof.git(root, 'rev-parse', proof.CLOUD_INIT_TERMINAL_BASE + '^{tree}')
            .decode().strip() == proof.CLOUD_INIT_TERMINAL_BASE_TREE
            and proof.git(root, 'rev-parse', proof.CLOUD_INIT_TERMINAL_SOURCE + '^{tree}')
            .decode().strip() == proof.CLOUD_INIT_TERMINAL_SOURCE_TREE)
    with proof.notification_acceptance_projection(root):
        with proof.cloud_init_terminal_projection(root):
            for path in proof.CLOUD_INIT_TERMINAL_MODIFIED:
                assert (proof.ordinary(root, path)
                        == proof.git(root, 'cat-file', 'blob',
                                     proof.CLOUD_INIT_TERMINAL_BASE + ':' + path))
    passed('Cloud-init terminal projection restores both exact predecessor paths')
    for attribute, label in (
        ('CLOUD_INIT_TERMINAL_BASE_TREE', 'base tree'),
        ('CLOUD_INIT_TERMINAL_SOURCE_TREE', 'source tree'),
        ('CLOUD_INIT_TERMINAL_SOURCE', 'source commit'),
        ('CLOUD_INIT_TERMINAL_REJECTED_PROOF', 'rejected proof commit'),
        ('CLOUD_INIT_TERMINAL_REJECTED_PROOF_TREE', 'rejected proof tree'),
        ('CLOUD_INIT_TERMINAL_CORRECTION_SOURCE', 'correction source commit'),
        ('CLOUD_INIT_TERMINAL_CORRECTION_SOURCE_TREE', 'correction source tree'),
        ('CLOUD_INIT_TERMINAL_PROOF', 'proof commit'),
        ('CLOUD_INIT_TERMINAL_PROOF_TREE', 'proof tree'),
        ('CLOUD_INIT_DISABLED_STAGE_SOURCE', 'disabled-stage source commit'),
        ('CLOUD_INIT_DISABLED_STAGE_SOURCE_TREE', 'disabled-stage source tree'),
        ('CLOUD_INIT_DISABLED_STAGE_CORRECTION_SOURCE', 'disabled-stage correction commit'),
        ('CLOUD_INIT_DISABLED_STAGE_CORRECTION_SOURCE_TREE', 'disabled-stage correction tree'),
        ('CLOUD_INIT_TERMINAL_SCRIPT_SHA256', 'script identity'),
        ('CLOUD_INIT_TERMINAL_CONTRACT_SHA256', 'contract identity'),
    ):
        original = getattr(proof, attribute)
        try:
            setattr(proof, attribute, '0' * len(original))
            def validate_cloud_init_current():
                with proof.notification_acceptance_projection(root):
                    return proof.validate_cloud_init_terminal_source(root)

            refuses('Refuses substituted cloud-init terminal ' + label,
                    validate_cloud_init_current)
        finally:
            setattr(proof, attribute, original)
    assert (proof.git(root, 'rev-parse', proof.PROJECT_PROGRESS_BASE + '^{tree}')
            .decode().strip() == proof.PROJECT_PROGRESS_BASE_TREE
            and proof.git(root, 'rev-parse', proof.PROJECT_PROGRESS_SOURCE + '^{tree}')
            .decode().strip() == proof.PROJECT_PROGRESS_SOURCE_TREE
            and proof.git(root, 'rev-parse',
                          proof.PROJECT_PROGRESS_PREDECESSOR_PROOF + '^{tree}')
            .decode().strip() == proof.PROJECT_PROGRESS_PREDECESSOR_PROOF_TREE
            and proof.git(root, 'rev-parse',
                          proof.PROJECT_PROGRESS_CORRECTION_SOURCE + '^{tree}')
            .decode().strip() == proof.PROJECT_PROGRESS_CORRECTION_SOURCE_TREE
            and proof.git(root, 'rev-parse',
                          proof.PROJECT_PROGRESS_REVIEW_PREDECESSOR_PROOF + '^{tree}')
            .decode().strip() == proof.PROJECT_PROGRESS_REVIEW_PREDECESSOR_PROOF_TREE
            and proof.git(root, 'rev-parse',
                          proof.PROJECT_PROGRESS_HANDOFF_CORRECTION_SOURCE + '^{tree}')
            .decode().strip() == proof.PROJECT_PROGRESS_HANDOFF_CORRECTION_SOURCE_TREE)
    progress_workflow = (root / '.github/workflows/project-progress.yml').read_text(
        encoding='utf-8')
    progress_inputs = (
        '.github/workflows/project-progress.yml',
        proof.PROJECT_PROGRESS_HANDOFF,
        proof.RECEIPT,
        'foundation/projects/ProjectProgressEvent.php',
        'foundation/projects/ProjectSnapshot.php',
        'site/includes/foundation/projects/ProjectProgressEvent.php',
        'site/includes/foundation/projects/ProjectSnapshot.php',
        proof.INVENTORY,
        proof.VERIFIER,
        proof.REGRESSION,
        'tests/project-progress/**',
    )
    assert all(progress_workflow.count("      - '" + path + "'") == 2
               for path in progress_inputs)
    passed('Focused project-progress workflow admits every application, handoff, and proof input')
    with proof.project_progress_projection(root):
        for path in proof.PROJECT_PROGRESS_MODIFIED:
            assert (proof.ordinary(root, path)
                    == proof.git(root, 'cat-file', 'blob',
                                 proof.PROJECT_PROGRESS_BASE + ':' + path))
        tracked = set(proof.git(root, 'ls-files', '-z').decode().split('\0')) - {''}
        assert proof.PROJECT_PROGRESS_ADDITIONS.isdisjoint(
            proof.actual_paths(root, tracked))
        assert (proof.canonical_inventory(root)
                == proof.git(root, 'cat-file', 'blob',
                             proof.PROJECT_PROGRESS_BASE + ':' + proof.INVENTORY))
    passed('Project-progress projection restores the exact qualified-main inventory and removes only its additions')
    assert (proof.git(root, 'rev-parse', proof.REL127_BASE + '^{tree}').decode().strip()
            == proof.REL127_BASE_TREE
            and proof.git(root, 'rev-parse', proof.REL127_SOURCE + '^{tree}').decode().strip()
            == proof.REL127_SOURCE_TREE)
    with proof.project_progress_projection(root):
        with proof.release_127_projection(root):
            for path in proof.REL127_MODIFIED:
                assert (proof.ordinary(root, path)
                        == proof.git(root, 'cat-file', 'blob', proof.REL127_BASE + ':' + path))
            tracked = set(proof.git(root, 'ls-files', '-z').decode().split('\0')) - {''}
            assert proof.REL127_ADDITIONS.isdisjoint(proof.actual_paths(root, tracked))
            assert (proof.ordinary(root, proof.REGRESSION)
                    == proof.actual_ordinary(root, proof.REGRESSION)
                    and proof.digest(proof.ordinary(root, proof.REGRESSION))
                    == proof.REGRESSION_SHA256)
    passed('REL-01 projection binds every exact modified path, removes only its additions, and leaves self-bound proof bytes current')

    for attribute, label in (
        ('REL127_BASE_TREE', 'base tree'),
        ('REL127_SOURCE_TREE', 'implementation tree'),
        ('REL127_SOURCE', 'implementation commit'),
    ):
        original = getattr(proof, attribute)
        try:
            setattr(proof, attribute, '0' * 40)
            refuses('Refuses substituted REL-01 ' + label,
                    lambda: proof.validate_release_127_source(root))
        finally:
            setattr(proof, attribute, original)
    for attribute, label in (
        ('PROJECT_PROGRESS_BASE_TREE', 'base tree'),
        ('PROJECT_PROGRESS_SOURCE_TREE', 'source tree'),
        ('PROJECT_PROGRESS_SOURCE', 'source commit'),
        ('PROJECT_PROGRESS_PREDECESSOR_PROOF', 'predecessor proof commit'),
        ('PROJECT_PROGRESS_PREDECESSOR_PROOF_TREE', 'predecessor proof tree'),
        ('PROJECT_PROGRESS_CORRECTION_SOURCE', 'correction source commit'),
        ('PROJECT_PROGRESS_CORRECTION_SOURCE_TREE', 'correction source tree'),
        ('PROJECT_PROGRESS_REVIEW_PREDECESSOR_PROOF', 'review predecessor proof commit'),
        ('PROJECT_PROGRESS_REVIEW_PREDECESSOR_PROOF_TREE', 'review predecessor proof tree'),
        ('PROJECT_PROGRESS_HANDOFF_CORRECTION_SOURCE', 'handoff correction source commit'),
        ('PROJECT_PROGRESS_HANDOFF_CORRECTION_SOURCE_TREE', 'handoff correction source tree'),
    ):
        original = getattr(proof, attribute)
        try:
            setattr(proof, attribute, '0' * 40)
            refuses('Refuses substituted project-progress ' + label,
                    lambda: proof.validate_project_progress_source(root))
        finally:
            setattr(proof, attribute, original)
    assert (0 < proof._LAST_PROJECTION_CACHE_MAX
            <= proof._PROJECTION_CACHE_LIMIT == 32768
            and not proof._ACTIVE_ROOT_KEYS)
    passed('Exact projection memoization is bounded and cleared after each full proof')
    assert len(proof._TREE_ENTRIES) <= proof.TREE_ENTRY_CACHE_LIMIT == 16
    passed('Immutable parsed-tree cache remains bounded after the complete proof')
    verifier_ast = ast.parse((root / proof.VERIFIER).read_text(encoding='utf-8'))
    terminal = next(node for node in verifier_ast.body
                    if isinstance(node, ast.FunctionDef)
                    and node.name == 'project_component_union_source')
    terminal_calls = [node.func.id for node in terminal.body
                      if isinstance(node, (ast.Assign, ast.Return))
                      for call in ([node.value] if isinstance(node, (ast.Assign, ast.Return)) else [])
                      if isinstance(call, ast.Call) and isinstance(call.func, ast.Name)
                      for node in [call]]
    assert terminal_calls == [
        'project_account_rate_boundary_source',
        'project_ux09_fixture_receipt',
        'project_ux09_identity_snapshot',
        'project_ux09_smtp_rejection',
        'project_ux09_mail_review',
        'project_ux09_cloud_proof',
        'project_ux09_security',
        'project_ux09_ci',
        'project_ux09',
    ]
    passed('Component union applies each terminal projection chain once in exact order')
    with tempfile.TemporaryDirectory(prefix='cwp-component-fast-path-') as folder:
        fixture = Path(folder)
        target = fixture / 'exact.txt'
        target.write_bytes(b'exact component bytes\n')
        subprocess.run(['git', 'init', '--quiet'], cwd=fixture, check=True)
        subprocess.run(['git', 'add', '--', 'exact.txt'], cwd=fixture, check=True)
        current = proof.ordinary(fixture, 'exact.txt')
        oid = hashlib.sha1(
            b'blob ' + str(len(current)).encode('ascii') + b'\0' + current).hexdigest()
        index_entries = proof.current_index_entries(fixture)
        assert proof.current_component_source_matches(
            fixture, 'exact.txt', current, '100644', oid, index_entries['exact.txt'])
        assert not proof.current_component_source_matches(
            fixture, 'exact.txt', current, '100644', '0' * 40,
            index_entries['exact.txt'])
        original_ordinary = proof.ordinary

        def raced_component(root_value, path):
            if Path(root_value) == fixture and path == 'exact.txt':
                return b'raced component bytes\n'
            return original_ordinary(root_value, path)

        proof.ordinary = raced_component
        try:
            refuses('Exact component-blob fast path refuses a mutable-source race',
                    lambda: proof.current_component_source_matches(
                        fixture, 'exact.txt', current, '100644', oid,
                        index_entries['exact.txt']))
        finally:
            proof.ordinary = original_ordinary
        target.write_bytes(b'staged unrelated bytes\n')
        subprocess.run(['git', 'add', '--', 'exact.txt'], cwd=fixture, check=True)
        assert not proof.current_component_source_matches(
            fixture, 'exact.txt', current, '100644', oid,
            proof.current_index_entries(fixture)['exact.txt'])
        refuses('Component union refuses a same-mode staged-blob race',
                lambda: proof.require_component_union_index_unchanged(
                    fixture, index_entries))
        target.write_bytes(current)
        subprocess.run(['git', 'add', '--', 'exact.txt'], cwd=fixture, check=True)
        restored_index = proof.current_index_entries(fixture)
        assert restored_index == index_entries
        subprocess.run(['git', 'update-index', '--chmod=+x', '--', 'exact.txt'],
                       cwd=fixture, check=True)
        assert not proof.current_component_source_matches(
            fixture, 'exact.txt', current, '100644', oid,
            proof.current_index_entries(fixture)['exact.txt'])
        refuses('Component union refuses a staged-mode race',
                lambda: proof.require_component_union_index_unchanged(
                    fixture, restored_index))
    passed('Exact component-blob fast path brackets unchanged bytes and modes')
    mail_projection = next(node for node in verifier_ast.body
                           if isinstance(node, ast.FunctionDef)
                           and node.name == 'project_mail_reply_forward_source')
    first_projection = next(node for node in mail_projection.body
                            if isinstance(node, ast.Assign))
    assert (isinstance(first_projection.value, ast.Call)
            and isinstance(first_projection.value.func, ast.Name)
            and first_projection.value.func.id == 'project_release_127_source')
    passed('Reply/Forward projection removes the exact REL-01 range before every historical adapter')
    with tempfile.TemporaryDirectory(prefix='cwp-composition-negative-') as folder:
        fixture = Path(folder) / 'source'
        subprocess.run(['git', 'clone', '--quiet', '--shared', '--no-checkout', '--local', str(root), str(fixture)], check=True)
        subprocess.run(['git', 'checkout', '--quiet', '--detach', proof.git(root, 'rev-parse', 'HEAD').decode().strip()], cwd=fixture, check=True)
        # The test also runs before the new comparison adapters are committed.
        # Copy only their finite reviewed bytes into the disposable checkout.
        for path in (proof.ADAPTERS | proof.REPAIRS | proof.ADDITIONS | proof.TICKET_ADDITIONS
                     | proof.DIAGNOSTIC_ADDITIONS | proof.DATABASE_WORKFLOW_ADDITIONS
                     | proof.CLOUD_INTAKE_ADDITIONS | proof.UX09_MODIFIED | proof.UX09_ADDITIONS
                     | proof.UX09_CI_MODIFIED
                     | proof.ONBOARDING_MODIFIED | proof.ONBOARDING_ADDITIONS
                     | proof.NOTIFICATION_ACCEPTANCE_MODIFIED
                     | proof.CLOUD_INIT_TERMINAL_MODIFIED
                     | {proof.VERIFIER, proof.RECEIPT, proof.REGRESSION, proof.INVENTORY,
                        proof.ONBOARDING_SOURCE, proof.UX09_SOURCE_RECEIPT,
                        'tools/acceptance-contract.json'}):
            target = fixture / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(proof.ordinary(root, path))
            target.chmod(0o644)
        proof.verify_current(fixture)
        passed('Disposable actual-filesystem fixture begins with the same validated composition')

        def cloud_init_terminal_tamper(path):
            target = fixture / path
            original = target.read_bytes()
            try:
                target.write_bytes(original + b'\n# unreviewed cloud-init admission\n')
                with proof.notification_acceptance_projection(fixture):
                    proof.validate_cloud_init_terminal_source(fixture)
            finally:
                target.write_bytes(original)

        refuses('Cloud-init terminal binding refuses changed preparation bytes',
                lambda: cloud_init_terminal_tamper(
                    '.github/scripts/prepare_ubuntu_apt.py'))
        refuses('Cloud-init terminal binding refuses changed contract bytes',
                lambda: cloud_init_terminal_tamper(
                    'tests/account_notification_workflow_contract.py'))
        original_cloud_paths = proof.CLOUD_INIT_TERMINAL_MODIFIED
        try:
            proof.CLOUD_INIT_TERMINAL_MODIFIED = original_cloud_paths | {'README.md'}
            def validate_cloud_init_paths():
                with proof.notification_acceptance_projection(fixture):
                    return proof.validate_cloud_init_terminal_source(fixture)

            refuses('Cloud-init terminal binding refuses an altered source path set',
                    validate_cloud_init_paths)
        finally:
            proof.CLOUD_INIT_TERMINAL_MODIFIED = original_cloud_paths

        def notification_acceptance_tamper(path):
            target = fixture / path
            original = target.read_bytes()
            try:
                target.write_bytes(original + b'\n# unreviewed notification acceptance\n')
                proof.validate_notification_acceptance_source(fixture)
            finally:
                target.write_bytes(original)

        refuses('Notification acceptance binding refuses changed workflow bytes',
                lambda: notification_acceptance_tamper(
                    '.github/workflows/account-notifications-acceptance.yml'))
        refuses('Notification acceptance binding refuses changed contract bytes',
                lambda: notification_acceptance_tamper(
                    'tests/account_notification_workflow_contract.py'))
        original_notification_paths = proof.NOTIFICATION_ACCEPTANCE_MODIFIED
        try:
            proof.NOTIFICATION_ACCEPTANCE_MODIFIED = (
                original_notification_paths | {'README.md'})
            refuses('Notification acceptance binding refuses an altered source path set',
                    lambda: proof.validate_notification_acceptance_source(fixture))
        finally:
            proof.NOTIFICATION_ACCEPTANCE_MODIFIED = original_notification_paths

        def project_progress_source_tamper(path):
            target = fixture / path
            original = target.read_bytes()
            try:
                target.write_bytes(original + b'\n# unreviewed project-progress source\n')
                proof.validate_project_progress_source(fixture)
            finally:
                target.write_bytes(original)

        refuses('Project-progress binding refuses modified inventory bytes',
                lambda: project_progress_source_tamper(proof.INVENTORY))
        refuses('Project-progress binding refuses changed added-file bytes',
                lambda: project_progress_source_tamper(
                    'foundation/projects/ProjectProgressEvent.php'))
        refuses('Project-progress binding refuses changed handoff-correction bytes',
                lambda: project_progress_source_tamper(
                    proof.PROJECT_PROGRESS_HANDOFF))

        def project_progress_line_ending_tamper(path):
            target = fixture / path
            original = target.read_bytes()
            assert b'\n' in original and b'\r\n' not in original
            try:
                target.write_bytes(original.replace(b'\n', b'\r\n'))
                proof.validate_project_progress_source(fixture)
            finally:
                target.write_bytes(original)

        refuses('Project-progress binding refuses normalized mirror line-ending drift',
                lambda: project_progress_line_ending_tamper(
                    'site/includes/foundation/projects/ProjectProgressEvent.php'))

        def project_progress_projection_race(path):
            target = fixture / path
            original = target.read_bytes()
            try:
                with proof.project_progress_projection(fixture):
                    target.write_bytes(original + b'\n# concurrent project-progress source\n')
            finally:
                target.write_bytes(original)

        refuses('Project-progress projection exit refuses a concurrent modified-file change',
                lambda: project_progress_projection_race(proof.INVENTORY))
        refuses('Project-progress projection exit refuses a concurrent added-file change',
                lambda: project_progress_projection_race(
                    'tests/project-progress/records.php'))

        original_project_proof_paths = proof.PROJECT_PROGRESS_PROOF_PATHS
        try:
            proof.PROJECT_PROGRESS_PROOF_PATHS = original_project_proof_paths | {'README.md'}
            refuses('Project-progress binding refuses an altered proof-successor path set',
                    lambda: proof.validate_project_progress_source(fixture))
        finally:
            proof.PROJECT_PROGRESS_PROOF_PATHS = original_project_proof_paths
        for attribute, label in (
            ('PROJECT_PROGRESS_CORRECTION_MODIFIED', 'modified path set'),
            ('PROJECT_PROGRESS_CORRECTION_ADDITIONS', 'addition path set'),
            ('PROJECT_PROGRESS_HANDOFF_CORRECTION_MODIFIED',
             'handoff-correction modified path set'),
        ):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, original | {'README.md'})
                refuses('Project-progress binding refuses an altered correction ' + label,
                        lambda: proof.validate_project_progress_source(fixture))
            finally:
                setattr(proof, attribute, original)
        passed('Project-progress negative cases restore exact source and proof bytes')

        def validate_release_fixture():
            with proof.project_progress_projection(fixture):
                return proof.validate_release_127_source(fixture)

        def release_source_tamper(path):
            target = fixture / path
            original = target.read_bytes()
            try:
                target.write_bytes(original + b'\n# unreviewed REL-01 source\n')
                validate_release_fixture()
            finally:
                target.write_bytes(original)

        refuses('REL-01 binding refuses modified implementation bytes',
                lambda: release_source_tamper('VERSION'))
        refuses('REL-01 binding refuses added-file bytes that differ from the exact implementation',
                lambda: release_source_tamper('tools/fetch_release_127_baseline.py'))

        def release_source_replace(path, old, new):
            target = fixture / path
            original = target.read_bytes()
            assert original.count(old) == 1
            try:
                target.write_bytes(original.replace(old, new, 1))
                validate_release_fixture()
            finally:
                target.write_bytes(original)

        refuses('REL-01 binding refuses restoration of the wrong mail-messages marker target',
                lambda: release_source_replace(
                    'tests/release_127_upgrade_probe.php',
                    b"'mail_messages_schema_version'=>'2'",
                    b"'mail_messages_schema_version'=>'1'"))
        refuses('REL-01 binding refuses restoration of the blanket version-one marker check',
                lambda: release_source_replace(
                    'tests/release_127_upgrade_probe.php',
                    b'foreach($requiredMarkers as $marker=>$version)if(setting($marker)!==$version)',
                    b"foreach($requiredMarkers as $marker=>$version)if(setting($marker)!=='1')"))
        refuses('REL-01 binding refuses restoration of null-coalescing pending-identity logic',
                lambda: release_source_replace(
                    'tests/release_127_upgrade_probe.php',
                    b"isset($rows['studio_client_registrations'])&&array_key_exists('verified_at',$rows['studio_client_registrations'])&&$rows['studio_client_registrations']['verified_at']===null",
                    b"($rows['studio_client_registrations']['verified_at']??false)===null"))
        refuses('REL-01 binding refuses restoration of the fulfillment-only timeout allowance',
                lambda: release_source_replace(
                    'tests/hosting_checkout_http_browser.py',
                    b"timeout = 360 if operation in ('payment-fulfill', 'payment-activate') else 180",
                    b"timeout = 360 if operation == 'payment-fulfill' else 180"))
        refuses('REL-01 binding refuses destructive re-extraction of the retained update stage',
                lambda: release_source_replace(
                    'tests/release_127_upgrade_acceptance.py',
                    b"check(label + ' recovery retains the exact staged successor for reapply',\n"
                    b'                              retained_snapshot(update_bytes, installed))',
                    b'extract_snapshot(update_bytes, installed)'))

        def release_projection_race(path):
            target = fixture / path
            original = target.read_bytes()
            try:
                with proof.project_progress_projection(fixture):
                    with proof.release_127_projection(fixture):
                        target.write_bytes(original + b'\n# concurrent unreviewed REL-01 source\n')
            finally:
                target.write_bytes(original)

        refuses('REL-01 projection exit refuses a concurrent modified-file change',
                lambda: release_projection_race('VERSION'))
        refuses('REL-01 projection exit refuses a concurrent added-file change',
                lambda: release_projection_race('tools/fetch_release_127_baseline.py'))

        def release_projection_mode_race(path):
            try:
                with proof.project_progress_projection(fixture):
                    with proof.release_127_projection(fixture):
                        subprocess.run(
                            ['git', 'update-index', '--chmod=+x', '--', path],
                            cwd=fixture, check=True, capture_output=True)
            finally:
                subprocess.run(
                    ['git', 'update-index', '--chmod=-x', '--', path],
                    cwd=fixture, check=True, capture_output=True)

        refuses('REL-01 projection exit refuses a concurrent index-mode change',
                lambda: release_projection_mode_race('VERSION'))

        def release_projection_blob_race(path):
            source_entry = proof.entries(fixture, proof.REL127_SOURCE)[path]
            try:
                with proof.project_progress_projection(fixture):
                    with proof.release_127_projection(fixture):
                        result = subprocess.run(
                            ['git', 'hash-object', '-w', '--stdin'], cwd=fixture,
                            input=b'unreviewed staged REL-01 source\n', capture_output=True,
                            check=True)
                        oid = result.stdout.decode().strip()
                        assert not result.stderr and re.fullmatch(r'[0-9a-f]{40}', oid)
                        subprocess.run(
                            ['git', 'update-index', '--cacheinfo',
                             source_entry[0] + ',' + oid + ',' + path],
                            cwd=fixture, check=True, capture_output=True)
            finally:
                subprocess.run(
                    ['git', 'update-index', '--cacheinfo',
                     source_entry[0] + ',' + source_entry[1] + ',' + path],
                    cwd=fixture, check=True, capture_output=True)

        refuses('REL-01 projection exit refuses a concurrent staged-blob change',
                lambda: release_projection_blob_race('VERSION'))
        passed('REL-01 projection brackets every modified and added source path, mode and index blob')

        original_proof_paths = proof.REL127_PROOF_PATHS
        try:
            proof.REL127_PROOF_PATHS = original_proof_paths | {'README.md'}
            refuses('REL-01 binding refuses an altered proof-successor path set',
                    validate_release_fixture)
        finally:
            proof.REL127_PROOF_PATHS = original_proof_paths

        regression = fixture / proof.REGRESSION
        original_regression = regression.read_bytes()
        try:
            regression.write_bytes(original_regression + b'\n# unreviewed proof hash\n')
            refuses('REL-01 receipt refuses an altered proof-successor hash',
                    lambda: proof.current_proof_receipt(fixture))
        finally:
            regression.write_bytes(original_regression)
        passed('REL-01 negative cases restore exact implementation and proof bytes')

        fixture_proof_files = {
            path: proof.ordinary(fixture, path)
            for path in (proof.RECEIPT, proof.VERIFIER, proof.REGRESSION)}
        preflight_paths, preflight_index = proof.tracked_source_preflight(
            fixture, fixture_proof_files)
        assert (set(preflight_paths) <= {proof.RECEIPT, proof.VERIFIER, proof.REGRESSION}
                and preflight_index == proof.current_index_entries(fixture))
        passed('Tracked-source preflight permits only already-validated proof-file drift')

        original_actual_ordinary = proof.actual_ordinary
        receipt_reads = []

        def racing_receipt(root, path):
            value = original_actual_ordinary(root, path)
            if Path(root) == fixture and path == proof.RECEIPT:
                receipt_reads.append(path)
                if len(receipt_reads) > 1:
                    return value + b'\n'
            return value

        proof.actual_ordinary = racing_receipt
        try:
            refuses('Strict receipt loader refuses proof-file change during validation',
                    lambda: proof.current_proof_receipt(fixture))
        finally:
            proof.actual_ordinary = original_actual_ordinary
        assert len(receipt_reads) > 1
        passed('Every current receipt consumer brackets exact proof bytes')

        def altered_diagnostic_artifact_batch_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_artifact_batch_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_ARTIFACT_BATCH_BASE', 'base'),
            ('DIAGNOSTIC_ARTIFACT_BATCH_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_ARTIFACT_BATCH_SOURCE', 'source'),
            ('DIAGNOSTIC_ARTIFACT_BATCH_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic artifact batch ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_artifact_batch_identity(attribute))

        def altered_diagnostic_blob_live_binding_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_blob_live_binding_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_BLOB_LIVE_BINDING_BASE', 'base'),
            ('DIAGNOSTIC_BLOB_LIVE_BINDING_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE', 'source'),
            ('DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic blob live-binding ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_blob_live_binding_identity(attribute))

        def altered_diagnostic_projection_cache_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_projection_cache_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_PROJECTION_CACHE_BASE', 'base'),
            ('DIAGNOSTIC_PROJECTION_CACHE_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_PROJECTION_CACHE_SOURCE', 'source'),
            ('DIAGNOSTIC_PROJECTION_CACHE_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic projection-cache ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_projection_cache_identity(attribute))

        def altered_diagnostic_projection_cache_race_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_projection_cache_race_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_PROJECTION_CACHE_RACE_BASE', 'base'),
            ('DIAGNOSTIC_PROJECTION_CACHE_RACE_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE', 'source'),
            ('DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic cache-race ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_projection_cache_race_identity(attribute))

        def altered_diagnostic_tracked_preflight_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_tracked_preflight_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_TRACKED_PREFLIGHT_BASE', 'base'),
            ('DIAGNOSTIC_TRACKED_PREFLIGHT_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE', 'source'),
            ('DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic tracked preflight ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_tracked_preflight_identity(attribute))

        def altered_diagnostic_tracked_preflight_cancellation_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_tracked_preflight_cancellation_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_BASE', 'base'),
            ('DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE', 'source'),
            ('DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic tracked cancellation ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_tracked_preflight_cancellation_identity(attribute))

        def altered_diagnostic_untracked_preflight_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_untracked_preflight_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_UNTRACKED_PREFLIGHT_BASE', 'base'),
            ('DIAGNOSTIC_UNTRACKED_PREFLIGHT_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE', 'source'),
            ('DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic untracked preflight ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_untracked_preflight_identity(attribute))

        def altered_diagnostic_protected_tree_preflight_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_protected_tree_preflight_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_BASE', 'base'),
            ('DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE', 'source'),
            ('DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic protected-tree preflight ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_protected_tree_preflight_identity(attribute))

        def altered_diagnostic_receipt_content_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_receipt_content_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_RECEIPT_CONTENT_BASE', 'base'),
            ('DIAGNOSTIC_RECEIPT_CONTENT_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_RECEIPT_CONTENT_SOURCE', 'source'),
            ('DIAGNOSTIC_RECEIPT_CONTENT_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic receipt content ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_receipt_content_identity(attribute))

        def altered_diagnostic_strict_receipt_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_strict_receipt_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_STRICT_RECEIPT_BASE', 'base'),
            ('DIAGNOSTIC_STRICT_RECEIPT_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_STRICT_RECEIPT_SOURCE', 'source'),
            ('DIAGNOSTIC_STRICT_RECEIPT_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic strict receipt ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_strict_receipt_identity(attribute))

        def altered_diagnostic_current_receipt_consumer_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_current_receipt_consumer_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_BASE', 'base'),
            ('DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE', 'source'),
            ('DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic current receipt consumer ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_current_receipt_consumer_identity(attribute))

        def altered_diagnostic_projection_chain_bracket_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_projection_chain_bracket_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_PROJECTION_CHAIN_BRACKET_BASE', 'base'),
            ('DIAGNOSTIC_PROJECTION_CHAIN_BRACKET_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_PROJECTION_CHAIN_BRACKET_SOURCE', 'source'),
            ('DIAGNOSTIC_PROJECTION_CHAIN_BRACKET_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic projection chain bracket ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_projection_chain_bracket_identity(attribute))

        def altered_diagnostic_projection_chain_transaction_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_projection_chain_transaction_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_PROJECTION_CHAIN_TRANSACTION_BASE', 'base'),
            ('DIAGNOSTIC_PROJECTION_CHAIN_TRANSACTION_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_PROJECTION_CHAIN_TRANSACTION_SOURCE', 'source'),
            ('DIAGNOSTIC_PROJECTION_CHAIN_TRANSACTION_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic projection chain transaction ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_projection_chain_transaction_identity(attribute))

        def altered_diagnostic_projection_lru_bound_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_projection_lru_bound_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_PROJECTION_LRU_BOUND_BASE', 'base'),
            ('DIAGNOSTIC_PROJECTION_LRU_BOUND_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_PROJECTION_LRU_BOUND_SOURCE', 'source'),
            ('DIAGNOSTIC_PROJECTION_LRU_BOUND_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic projection LRU bound ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_projection_lru_bound_identity(attribute))

        def altered_diagnostic_projection_transaction_order_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_projection_transaction_order_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_PROJECTION_TRANSACTION_ORDER_BASE', 'base'),
            ('DIAGNOSTIC_PROJECTION_TRANSACTION_ORDER_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_PROJECTION_TRANSACTION_ORDER_SOURCE', 'source'),
            ('DIAGNOSTIC_PROJECTION_TRANSACTION_ORDER_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic projection transaction order ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_projection_transaction_order_identity(attribute))

        def altered_diagnostic_projection_refusal_reset_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_diagnostic_projection_refusal_reset_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('DIAGNOSTIC_PROJECTION_REFUSAL_RESET_BASE', 'base'),
            ('DIAGNOSTIC_PROJECTION_REFUSAL_RESET_BASE_TREE', 'base tree'),
            ('DIAGNOSTIC_PROJECTION_REFUSAL_RESET_SOURCE', 'source'),
            ('DIAGNOSTIC_PROJECTION_REFUSAL_RESET_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted diagnostic projection refusal reset ' + label,
                    lambda attribute=attribute:
                    altered_diagnostic_projection_refusal_reset_identity(attribute))

        def altered_hosting_inbox_reload_budget_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_hosting_inbox_reload_budget_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('HOSTING_INBOX_RELOAD_BUDGET_BASE', 'base'),
            ('HOSTING_INBOX_RELOAD_BUDGET_BASE_TREE', 'base tree'),
            ('HOSTING_INBOX_RELOAD_BUDGET_SOURCE', 'source'),
            ('HOSTING_INBOX_RELOAD_BUDGET_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted hosted inbox reload budget ' + label,
                    lambda attribute=attribute:
                    altered_hosting_inbox_reload_budget_identity(attribute))

        def altered_hosting_inbox_reload_completion_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_hosting_inbox_reload_completion_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('HOSTING_INBOX_RELOAD_COMPLETION_BASE', 'base'),
            ('HOSTING_INBOX_RELOAD_COMPLETION_BASE_TREE', 'base tree'),
            ('HOSTING_INBOX_RELOAD_COMPLETION_SOURCE', 'source'),
            ('HOSTING_INBOX_RELOAD_COMPLETION_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted hosted inbox reload completion ' + label,
                    lambda attribute=attribute:
                    altered_hosting_inbox_reload_completion_identity(attribute))

        artifact_helper = fixture / proof.DIAGNOSTIC_ARTIFACT_BATCH_PATH
        artifact_helper_current = artifact_helper.read_bytes()
        artifact_helper_predecessor = proof.git(
            root, 'cat-file', 'blob',
            proof.DIAGNOSTIC_ARTIFACT_BATCH_BASE + ':'
            + proof.DIAGNOSTIC_ARTIFACT_BATCH_PATH)
        try:
            artifact_helper.write_bytes(artifact_helper_predecessor)
            refuses('Refuses exact predecessor diagnostic artifact helper rollback',
                    lambda: proof.validate_diagnostic_artifact_batch_source(fixture))
        finally:
            artifact_helper.write_bytes(artifact_helper_current)

        fixture_head = proof.git(fixture, 'rev-parse', 'HEAD').decode().strip()
        fixture_parent = proof.git(fixture, 'rev-parse', fixture_head + '^').decode().strip()
        root_key = str(fixture.resolve())
        cache_key = (root_key, ('merge-base', '--is-ancestor', fixture_head, fixture_head))
        proof._IMMUTABLE_GIT_RESULTS.pop(cache_key, None)
        subprocess.run(['git', 'checkout', '--quiet', '--detach', fixture_parent],
                       cwd=fixture, check=True)
        try:
            proof.git(fixture, 'merge-base', '--is-ancestor', fixture_head, 'HEAD')
            passed('Pinned HEAD query executes the pinned object ID after the live ref moves')
        finally:
            subprocess.run(['git', 'checkout', '--quiet', '--detach', fixture_head],
                           cwd=fixture, check=True)
        proof.pin_head(fixture)

        version_spec = fixture_head + ':VERSION'
        version_blob = proof.git(fixture, 'rev-parse', version_spec).decode().strip()
        version_bytes = proof.git(fixture, 'cat-file', 'blob', version_spec)
        replacement = subprocess.run(
            ['git', 'hash-object', '-w', '--stdin'], cwd=fixture,
            input=b'9.99.99\n', capture_output=True, check=True).stdout.decode().strip()
        subprocess.run(['git', 'replace', version_blob, replacement], cwd=fixture, check=True)
        try:
            proof._IMMUTABLE_GIT_RESULTS.pop(
                (root_key, ('cat-file', 'blob', version_spec)), None)
            assert proof.git(fixture, 'cat-file', 'blob', version_spec) == version_bytes
            passed('Immutable object queries ignore Git replacement refs')
            refuses('Refuses Git replacement refs during composition verification',
                    lambda: proof.pin_head(fixture))
        finally:
            subprocess.run(['git', 'replace', '-d', version_blob], cwd=fixture,
                           check=True, capture_output=True)
        proof.pin_head(fixture)

        def modified(path, operation):
            target = fixture / path
            original = target.read_bytes()
            permission = target.stat().st_mode & 0o777
            try:
                operation(target)
                proof.verify_current(fixture)
            finally:
                if target.is_symlink():
                    target.unlink()
                target.write_bytes(original)
                target.chmod(permission)

        def executable_mode(path, validation=None):
            validation = validation or (lambda: proof.verify_current(fixture))
            target = fixture / path
            if os.name == 'nt':
                row = subprocess.check_output(
                    ['git', 'ls-files', '--stage', '--', path], cwd=fixture,
                    text=True).rstrip('\n')
                metadata, indexed_path = row.split('\t', 1)
                permission, oid, stage = metadata.split(' ')
                assert indexed_path == path and stage == '0'
                try:
                    subprocess.run(
                        ['git', 'update-index', '--chmod=+x', '--', path],
                        cwd=fixture, check=True, capture_output=True)
                    validation()
                finally:
                    subprocess.run(
                        ['git', 'update-index', '--add', '--cacheinfo',
                         permission + ',' + oid + ',' + indexed_path],
                        cwd=fixture, check=True, capture_output=True)
                return
            permission = target.stat().st_mode & 0o777
            try:
                target.chmod(0o755)
                validation()
            finally:
                target.chmod(permission)

        early_path = 'site/app/billing/software_client.php'
        original_validator = proof.validate_application_reply_folder_route
        reached_historical_validation = []

        def unexpected_historical_validation(root):
            reached_historical_validation.append(root)
            raise AssertionError('Historical validation ran before tracked-source refusal')

        proof.validate_application_reply_folder_route = unexpected_historical_validation
        try:
            refuses('Refuses unstaged tracked drift before historical validation',
                    lambda: modified(
                        early_path,
                        lambda target: target.write_bytes(
                            target.read_bytes() + b'\n# preflight mutation\n')))
            assert not reached_historical_validation
        finally:
            proof.validate_application_reply_folder_route = original_validator
        passed('Tracked-source preflight precedes expensive historical validation')

        target = fixture / early_path
        original = target.read_bytes()
        permission = target.stat().st_mode & 0o777
        try:
            target.write_bytes(original + b'\n# staged preflight mutation\n')
            subprocess.run(['git', 'add', '--', early_path], cwd=fixture, check=True)
            refuses('Refuses staged tracked drift before historical projection',
                    lambda: proof.verify_current(fixture))
        finally:
            subprocess.run(['git', 'reset', '--quiet', 'HEAD', '--', early_path],
                           cwd=fixture, check=True)
            target.write_bytes(original)
            target.chmod(permission)

        try:
            target.write_bytes(original + b'\n# staged cancellation mutation\n')
            subprocess.run(['git', 'add', '--', early_path], cwd=fixture, check=True)
            target.write_bytes(original)
            refuses('Refuses offsetting staged and unstaged tracked drift',
                    lambda: proof.verify_current(fixture))
        finally:
            subprocess.run(['git', 'reset', '--quiet', 'HEAD', '--', early_path],
                           cwd=fixture, check=True)
            target.write_bytes(original)
            target.chmod(permission)

        original_preflight = proof.tracked_source_preflight

        def stage_after_authenticated_preflight(root_value, proof_files):
            result = original_preflight(root_value, proof_files)
            if Path(root_value) == fixture:
                target.write_bytes(original + b'\n# post-preflight staged mutation\n')
                subprocess.run(['git', 'add', '--', early_path], cwd=fixture, check=True)
                target.write_bytes(original)
            return result

        proof.tracked_source_preflight = stage_after_authenticated_preflight
        try:
            refuses('Full verification refuses a staged mutation after authenticated preflight',
                    lambda: proof.verify_current(fixture))
        finally:
            proof.tracked_source_preflight = original_preflight
            target.write_bytes(original)
            subprocess.run(['git', 'add', '--', early_path], cwd=fixture, check=True)
            target.chmod(permission)

        proof_target = fixture / proof.RECEIPT
        proof_original = proof_target.read_bytes()
        try:
            proof_target.write_bytes(proof_original + b'\n')
            subprocess.run(['git', 'add', '--', proof.RECEIPT], cwd=fixture, check=True)
            proof_target.write_bytes(proof_original)
            refuses('Refuses staged proof bytes that differ from validated worktree bytes',
                    lambda: proof.verify_current(fixture))
        finally:
            subprocess.run(['git', 'reset', '--quiet', 'HEAD', '--', proof.RECEIPT],
                           cwd=fixture, check=True)
            proof_target.write_bytes(proof_original)

        try:
            subprocess.run(['git', 'update-index', '--chmod=+x', '--', proof.REGRESSION],
                           cwd=fixture, check=True)
            refuses('Refuses a staged executable mode for a proof file',
                    lambda: proof.verify_current(fixture))
        finally:
            subprocess.run(['git', 'reset', '--quiet', 'HEAD', '--', proof.REGRESSION],
                           cwd=fixture, check=True)

        def remove_receipt_inverse(target):
            data = json.loads(target.read_bytes())
            data['adapters']['tests/tickets_integration_contract.py']['edits'].pop()
            target.write_text(json.dumps(data))

        reached_historical_validation.clear()
        proof.validate_application_reply_folder_route = unexpected_historical_validation
        try:
            refuses('Refuses changed canonical receipt content before historical validation',
                    lambda: modified(proof.RECEIPT, remove_receipt_inverse))
            assert not reached_historical_validation
        finally:
            proof.validate_application_reply_folder_route = original_validator
        passed('Canonical receipt content is bound before historical validation')

        def replace_receipt_once(target, before, after):
            value = target.read_bytes()
            assert value.count(before) == 1
            target.write_bytes(value.replace(before, after, 1))

        duplicate_cases = (
            ('top-level duplicate name',
             b'{\n  "format": 6,',
             b'{\n  "format": 999,\n  "format": 6,'),
            ('nested duplicate name',
             b'    "account": {\n      "commit": ',
             b'    "account": {\n      "commit": "' + b'0' * 40
             + b'",\n      "commit": '),
            ('non-finite constant', b'"format": 6', b'"format": NaN'),
        )
        reached_historical_validation.clear()
        proof.validate_application_reply_folder_route = unexpected_historical_validation
        try:
            for label, before, after in duplicate_cases:
                refuses('Refuses composition receipt ' + label,
                        lambda before=before, after=after: modified(
                            proof.RECEIPT,
                            lambda target: replace_receipt_once(
                                target, before, after)))
                assert not reached_historical_validation
        finally:
            proof.validate_application_reply_folder_route = original_validator
        passed('Strict receipt parsing precedes historical validation')

        for name, path in (
            ('current software authority application', 'site/app/billing/software_client.php'),
            ('current finance application', 'site/app/billing/finance_subscription.php'),
            ('current notification application', 'site/app/notifications/outbox.php'),
            ('retained native software assertions', 'tests/software_commerce_native.php'),
            ('standalone software source proof', 'tests/software_authority_provenance.py'),
            ('standalone finance source proof', 'tests/finance_recovery_provenance.py'),
            ('original notification receipt', 'docs/NOTIFICATION_SETTINGS_FRESHNESS_CORRECTION.json'),
            ('protected complete application workflow', '.github/workflows/application-tests.yml'),
            ('current artifact source binding contract', 'tools/acceptance-contract.json'),
            ('bounded buffered SMTP acceptance fixture', 'tests/mail_submission_server.py'),
            ('bounded Sent-copy acceptance fixture', 'tests/mail_submissions_cases.py'),
            ('current canonical installer inventory', proof.INVENTORY),
            ('finite comparison adapter', 'tests/provider_browser_fixture_provenance.py'),
            ('composition verifier', proof.VERIFIER),
            ('actual-filesystem regression suite', proof.REGRESSION),
        ):
            refuses('Refuses altered ' + name, lambda path=path: modified(path, lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed source\n')))

        smtp_fixture = 'tests/mail_submission_server.py'
        refuses('Refuses byte-at-a-time SMTP fixture regression',
                lambda: modified(smtp_fixture, lambda target: target.write_bytes(
                    target.read_bytes().replace(b's.recv(65536)', b's.recv(1)', 1))))
        refuses('Refuses reduced SMTP fixture DATA ceiling',
                lambda: modified(smtp_fixture, lambda target: target.write_bytes(
                    target.read_bytes().replace(b"len(attempt['data'])<=1048576",
                                                b"len(attempt['data'])<=65536", 1))))

        sent_copy_fixture = 'tests/mail_submissions_cases.py'
        refuses('Refuses removal of the large Sent-copy APPEND fixture mode',
                lambda: modified(sent_copy_fixture, lambda target: target.write_bytes(
                    target.read_bytes().replace(
                        b"    inbox_server.mode = 'append'\n    r = member.post(attach_copy_page,",
                        b"    r = member.post(attach_copy_page,", 1))))

        reply_fixture = 'tests/mail_reply_cases.py'
        refuses('Refuses removal of the Reply fixture folder identity',
                lambda: modified(reply_fixture, lambda target: target.write_bytes(
                    target.read_bytes().replace(b", 'folder': 'INBOX'}\n", b"}\n", 1))))

        path = 'site/app/billing/software_client.php'
        refuses('Refuses removal of an actual current application file',
                lambda: modified(path, lambda target: target.unlink()))

        original_filesystem_mode = proof.filesystem_mode
        original_index_modes = proof._CURRENT_INDEX_MODES
        try:
            proof.filesystem_mode = lambda root, candidate: (
                '100755' if candidate == path else original_filesystem_mode(root, candidate))
            assert proof.windows_index_mode(path, {path: '100644'}) == '100644'
            assert proof.windows_index_mode(path, {path: '100755'}) == '100755'
            refuses('Windows mode authority refuses a missing tracked index entry',
                    lambda: proof.windows_index_mode(path, {}))
            refuses('Windows mode authority refuses an invalid tracked index mode',
                    lambda: proof.windows_index_mode(path, {path: '100664'}))
            if os.name == 'nt':
                proof._CURRENT_INDEX_MODES = {path: '100644'}
                assert proof.mode(fixture, path) == '100644'
            else:
                assert proof.mode(fixture, path) == '100755'
        finally:
            proof.filesystem_mode = original_filesystem_mode
            proof._CURRENT_INDEX_MODES = original_index_modes
        passed('Tracked mode authority uses exact Windows index modes and POSIX filesystem modes')

        refuses('Refuses an unexpected executable mode on actual application source',
                lambda: executable_mode(path))

        def symlink(target):
            target.unlink()
            target.symlink_to('software_orders.php')

        refuses('Refuses a real symlink replacing current application source', lambda: modified(path, symlink))

        def addition(path):
            target = fixture / path
            target.write_bytes(b'<?php /* unreviewed addition */\n')
            try:
                proof.verify_current(fixture)
            finally:
                target.unlink()

        reached_historical_validation.clear()
        proof.validate_application_reply_folder_route = unexpected_historical_validation
        try:
            refuses('Refuses an untracked additional source file',
                    lambda: addition('tests/unreviewed_composition.py'))
            assert not reached_historical_validation
        finally:
            proof.validate_application_reply_folder_route = original_validator
        passed('Untracked-source preflight precedes expensive historical validation')
        refuses('Refuses a hidden additional shipping file', lambda: addition('site/.unreviewed-composition.php'))

        ignored_ops = fixture / 'ops/twentyi-cloud-root/__pycache__/unreviewed_source.py'
        ignored_parent_existed = ignored_ops.parent.exists()
        ignored_ops.parent.mkdir(parents=True, exist_ok=True)
        ignored_ops.write_bytes(b'raise RuntimeError("unreviewed ignored source")\n')
        reached_historical_validation.clear()
        proof.validate_application_reply_folder_route = unexpected_historical_validation
        try:
            assert subprocess.run(
                ['git', 'check-ignore', '--quiet', '--',
                 ignored_ops.relative_to(fixture).as_posix()], cwd=fixture).returncode == 0
            refuses('Refuses ignored source under a tracked operations tree',
                    lambda: proof.verify_current(fixture))
            assert not reached_historical_validation
        finally:
            proof.validate_application_reply_folder_route = original_validator
            ignored_ops.unlink()
            if not ignored_parent_existed:
                ignored_ops.parent.rmdir()
        passed('Ignored operations source is inventoried before historical validation')

        for path in sorted(proof.UX09_ADDITIONS):
            refuses('Refuses altered exact UX-09 addition ' + path,
                    lambda path=path: modified(
                        path, lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed UX-09 source\n')))
            refuses('Refuses missing exact UX-09 addition ' + path,
                    lambda path=path: modified(path, lambda target: target.unlink()))
        refuses('Refuses altered exact UX-09 shipping integration',
                lambda: modified('site/app/clients/accounts.php',
                                 lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed identity authority\n')))
        refuses('Refuses altered exact UX-09 CI successor',
                lambda: modified('tests/domain_registration_drafts_native.php',
                                 lambda target: target.write_bytes(target.read_bytes() + b'\n// unreviewed fixture change\n')))
        refuses('Refuses altered exact UX-09 security successor',
                lambda: modified('site/app/mail/transport.php',
                                 lambda target: target.write_bytes(target.read_bytes() + b'\n// unreviewed delivery change\n')))
        refuses('Refuses altered exact UX-09 cloud-proof successor',
                lambda: modified('tests/cloud_site_intake_wiring.py',
                                 lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed projection change\n')))

        def altered_ux09_receipt(change):
            def rewrite(target):
                data = json.loads(target.read_bytes())
                change(data)
                target.write_text(json.dumps(data))
            modified(proof.UX09_SOURCE_RECEIPT, rewrite)

        refuses('Refuses substituting the UX-09 resulting-main base',
                lambda: altered_ux09_receipt(lambda data: data.__setitem__('base_commit', '0' * 40)))
        refuses('Refuses substituting the UX-09 implementation source',
                lambda: altered_ux09_receipt(lambda data: data.__setitem__('source_commit', '0' * 40)))
        refuses('Refuses omitting an exact UX-09 addition admission',
                lambda: altered_ux09_receipt(lambda data: data['added'].pop('site/app/clients/public_identity.php')))

        for name, path in (
            ('Reply/Forward submission runtime', 'site/app/mail/submissions.php'),
            ('Reply/Forward canonical class', 'foundation/mail-compose/ReplyDraft.php'),
            ('Reply/Forward shipping mirror', 'site/includes/foundation/mail-compose/ReplyDraft.php'),
            ('Reply/Forward shared schema registry', 'site/app/settings/database_schemas.php'),
            ('Reply/Forward shared staff authority', 'site/app/staff/accounts.php'),
            ('Reply/Forward canonical installer inventory', proof.INVENTORY),
        ):
            refuses('Refuses altered exact ' + name,
                    lambda path=path: modified(
                        path, lambda target: target.write_bytes(
                            target.read_bytes() + b'\n# unreviewed Reply/Forward source\n')))
        refuses('Refuses a missing Reply/Forward addition',
                lambda: modified('site/app/mail/replies.php', lambda target: target.unlink()))
        refuses('Refuses an executable mode on a Reply/Forward addition',
                lambda: executable_mode('foundation/mail-compose/ReplyDraft.php'))
        refuses('Refuses an alternate Reply/Forward workflow trigger',
                lambda: modified('.github/workflows/mail-copied-draft-native.yml',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\nworkflow_dispatch:\n')))

        def altered_mail_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_reply_forward_source(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted Reply/Forward source tree',
                lambda: altered_mail_identity('MAIL_REPLY_FORWARD_SOURCE_TREE'))
        refuses('Refuses a substituted Reply/Forward prepared parent',
                lambda: altered_mail_identity('MAIL_REPLY_FORWARD_PREPARED'))

        def altered_mail_workflow_fix_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_reply_forward_workflow_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted Reply/Forward workflow-fix source tree',
                lambda: altered_mail_workflow_fix_identity(
                    'MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted Reply/Forward workflow-fix parent',
                lambda: altered_mail_workflow_fix_identity(
                    'MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE'))

        def altered_mail_thread_header_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_thread_header_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted structured-thread source tree',
                lambda: altered_mail_thread_header_identity(
                    'MAIL_THREAD_HEADER_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted structured-thread direct parent',
                lambda: altered_mail_thread_header_identity(
                    'MAIL_THREAD_HEADER_FIX_BASE'))
        refuses('Refuses altered structured-thread rendering source',
                lambda: modified('foundation/mail-submission/OfflineMailer.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n// unreviewed header rendering\n')))

        def altered_mail_thread_alias_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_thread_alias_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted thread-alias security source tree',
                lambda: altered_mail_thread_alias_identity(
                    'MAIL_THREAD_ALIAS_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted thread-alias security direct parent',
                lambda: altered_mail_thread_alias_identity(
                    'MAIL_THREAD_ALIAS_FIX_BASE'))
        refuses('Refuses altered thread-alias security source',
                lambda: modified('site/includes/foundation/mail-submission/OfflineMailer.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n// unreviewed alias handling\n')))

        def altered_mail_native_fixture_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_native_fixture_policy_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted native fixture-policy source tree',
                lambda: altered_mail_native_fixture_identity(
                    'MAIL_NATIVE_FIXTURE_POLICY_SOURCE_TREE'))
        refuses('Refuses a substituted native fixture-policy direct parent',
                lambda: altered_mail_native_fixture_identity(
                    'MAIL_NATIVE_FIXTURE_POLICY_BASE'))
        refuses('Refuses altered native copied-draft fixture policy',
                lambda: modified('tests/mail_copied_draft_create_native.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n// unreviewed native fixture\n')))

        def altered_mail_mysql_trigger_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_mysql_trigger_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted mail MySQL trigger-fixture source tree',
                lambda: altered_mail_mysql_trigger_identity(
                    'MAIL_MYSQL_TRIGGER_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted mail MySQL trigger-fixture direct parent',
                lambda: altered_mail_mysql_trigger_identity(
                    'MAIL_MYSQL_TRIGGER_FIX_BASE'))
        refuses('Refuses altered mail MySQL trigger-fixture workflow source',
                lambda: modified('.github/workflows/mail-copied-draft-native.yml',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n# unreviewed trigger permission\n')))

        def altered_mail_reply_form_helper_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_reply_form_helper_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted mail reply form-helper source tree',
                lambda: altered_mail_reply_form_helper_identity(
                    'MAIL_REPLY_FORM_HELPER_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted mail reply form-helper direct parent',
                lambda: altered_mail_reply_form_helper_identity(
                    'MAIL_REPLY_FORM_HELPER_FIX_BASE'))
        refuses('Refuses altered mail reply form-helper fixture source',
                lambda: modified('tests/mail_reply_cases.py',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n# unreviewed helper binding\n')))

        def altered_mail_copy_controls_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_copy_controls_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted mail copy-controls source tree',
                lambda: altered_mail_copy_controls_identity(
                    'MAIL_COPY_CONTROLS_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted mail copy-controls direct parent',
                lambda: altered_mail_copy_controls_identity(
                    'MAIL_COPY_CONTROLS_FIX_BASE'))
        refuses('Refuses altered mail copy-controls controller source',
                lambda: modified('site/admin/controllers/mail_message.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n// unreviewed copy authority\n')))
        refuses('Refuses altered mail copy-controls contract source',
                lambda: modified('tests/mail_copied_draft_create_adapter.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n// unreviewed copy contract\n')))

        def altered_mail_consolidated_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * len(original))
                proof._MAIL_CONSOLIDATED_CHANGES.clear()
                proof.validate_mail_consolidated_source(fixture)
            finally:
                setattr(proof, attribute, original)
                proof._MAIL_CONSOLIDATED_CHANGES.clear()

        refuses('Refuses a substituted consolidated mail source tree',
                lambda: altered_mail_consolidated_identity(
                    'MAIL_CONSOLIDATED_SOURCE_TREE'))
        refuses('Refuses a substituted consolidated mail base tree',
                lambda: altered_mail_consolidated_identity(
                    'MAIL_CONSOLIDATED_BASE_TREE'))
        refuses('Refuses a substituted consolidated mail path/status digest',
                lambda: altered_mail_consolidated_identity(
                    'MAIL_CONSOLIDATED_DIFF_SHA256'))
        refuses('Refuses altered consolidated formatted-document source',
                lambda: modified('site/app/mail/document.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n// unreviewed formatted document\n')))
        refuses('Refuses altered consolidated draft migration source',
                lambda: modified('site/database/migrations/mail_drafts_migrate.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n// unreviewed migration\n')))

        def altered_mail_consolidated_review_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * len(original))
                proof.validate_mail_consolidated_review_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted consolidated mail review source tree',
                lambda: altered_mail_consolidated_review_identity(
                    'MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted consolidated mail review direct parent',
                lambda: altered_mail_consolidated_review_identity(
                    'MAIL_CONSOLIDATED_REVIEW_FIX_BASE'))
        refuses('Refuses altered consolidated mail review storage bound',
                lambda: modified('foundation/mail-compose/DraftOrigin.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes().replace(b'65535', b'65536', 1))))
        refuses('Refuses altered consolidated mail review readiness marker',
                lambda: modified('site/app/mail/drafts.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes().replace(b"['mail_drafts_html_ready']", b"['mail_drafts_schema_version']", 1))))

        def altered_mail_review_recovery_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * len(original))
                proof.validate_mail_consolidated_review_recovery(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted consolidated mail review-recovery source tree',
                lambda: altered_mail_review_recovery_identity(
                    'MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE_TREE'))
        refuses('Refuses a substituted consolidated mail review-recovery direct parent',
                lambda: altered_mail_review_recovery_identity(
                    'MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE'))
        refuses('Refuses altered formatted-mail stale-marker invalidation',
                lambda: modified('site/database/migrations/mail_drafts_migrate.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes().replace(b"'html_repair_pending'", b"'setup'", 1))))

        def altered_mail_sent_copy_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * len(original))
                proof.validate_mail_sent_copy_source(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted Sent-copy source tree',
                lambda: altered_mail_sent_copy_identity('MAIL_SENT_COPY_SOURCE_TREE'))
        refuses('Refuses a substituted Sent-copy direct parent',
                lambda: altered_mail_sent_copy_identity('MAIL_SENT_COPY_BASE'))
        refuses('Refuses weakened Sent-copy uncertainty replay protection',
                lambda: modified('tests/mail_submissions_native.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes().replace(
                                         b'Uncertain Sent copy can never be replayed',
                                         b'Uncertain Sent copy may be replayed', 1))))

        def altered_mail_sent_copy_origin_fix_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * len(original))
                proof.validate_mail_sent_copy_origin_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted Sent-copy copied-origin fix tree',
                lambda: altered_mail_sent_copy_origin_fix_identity('MAIL_SENT_COPY_ORIGIN_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted Sent-copy copied-origin fix parent',
                lambda: altered_mail_sent_copy_origin_fix_identity('MAIL_SENT_COPY_ORIGIN_FIX_BASE'))
        refuses('Refuses a removed Sent-copy copied-origin transport recheck',
                lambda: modified('site/app/mail/copies.php',
                                 lambda target: target.write_bytes(
                                     target.read_bytes().replace(
                                         b'mail_submissions_origin($saved,$prepared,$actor);',
                                         b'/* copied-origin check removed */', 1))))

        def altered_mail_reply_session_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_reply_session_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted mail reply session-policy source tree',
                lambda: altered_mail_reply_session_identity(
                    'MAIL_REPLY_SESSION_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted mail reply session-policy direct parent',
                lambda: altered_mail_reply_session_identity(
                    'MAIL_REPLY_SESSION_FIX_BASE'))
        refuses('Refuses altered mail reply session-policy fixture source',
                lambda: modified('tests/mail_reply_cases.py',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n# unreviewed session fixture\n')))
        refuses('Refuses an altered mail backup predecessor projection',
                lambda: modified('tests/backups_acceptance_wiring.py',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n# unreviewed mail projection\n')))

        def altered_mail_backup_projection_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_backup_projection_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted mail backup-projection source tree',
                lambda: altered_mail_backup_projection_identity(
                    'MAIL_BACKUP_PROJECTION_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted mail backup-projection parent',
                lambda: altered_mail_backup_projection_identity(
                    'MAIL_BACKUP_PROJECTION_FIX_BASE'))
        refuses('Refuses an altered mail public-identity artifact projection',
                lambda: modified('tests/acceptance_artifacts_test.py',
                                 lambda target: target.write_bytes(
                                     target.read_bytes() + b'\n# unreviewed identity projection\n')))

        def altered_mail_artifact_projection_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_artifact_projection_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted mail artifact-projection source tree',
                lambda: altered_mail_artifact_projection_identity(
                    'MAIL_ARTIFACT_PROJECTION_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted mail artifact-projection parent',
                lambda: altered_mail_artifact_projection_identity(
                    'MAIL_ARTIFACT_PROJECTION_FIX_BASE'))

        def altered_mail_artifact_overlap_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_mail_artifact_overlap_fix(fixture)
            finally:
                setattr(proof, attribute, original)

        refuses('Refuses a substituted mail artifact-overlap source tree',
                lambda: altered_mail_artifact_overlap_identity(
                    'MAIL_ARTIFACT_OVERLAP_FIX_SOURCE_TREE'))
        refuses('Refuses a substituted mail artifact-overlap parent',
                lambda: altered_mail_artifact_overlap_identity(
                    'MAIL_ARTIFACT_OVERLAP_FIX_BASE'))
        project_source = proof.git(
            fixture, 'cat-file', 'blob',
            proof.PROJECT_PROGRESS_SOURCE + ':' + proof.INVENTORY)
        project_base = proof.git(
            fixture, 'cat-file', 'blob',
            proof.PROJECT_PROGRESS_BASE + ':' + proof.INVENTORY)
        assert (proof.project_project_progress_source(
                    fixture, proof.INVENTORY, project_source) == project_base
                and proof.project_project_progress_source(
                    fixture, proof.INVENTORY, project_base) == project_base)
        passed('Project-progress direct projection restores exact qualified-main inventory bytes')

        def tampered_project_progress_projection():
            target = fixture / proof.INVENTORY
            original = target.read_bytes()
            try:
                tampered = original + b'\n# unreviewed direct project-progress source\n'
                target.write_bytes(tampered)
                proof.project_project_progress_source(fixture, proof.INVENTORY, tampered)
            finally:
                target.write_bytes(original)

        refuses('Project-progress direct projection refuses unreviewed live source bytes',
                tampered_project_progress_projection)
        release_overlap_paths = (
            'site/app/settings/database_schemas.php',
            proof.INVENTORY,
            'tests/database_schema_registry_policy.php',
            'tests/database_schema_view_policy.php',
        )
        for path in release_overlap_paths:
            release_source = proof.git(
                fixture, 'cat-file', 'blob', proof.REL127_SOURCE + ':' + path)
            release_base = proof.git(
                fixture, 'cat-file', 'blob', proof.REL127_BASE + ':' + path)
            assert (proof.project_release_127_source(
                        fixture, path, release_source) == release_base
                    and proof.project_release_127_source(
                        fixture, path, release_base) == release_base)
            passed('REL-01 direct projection restores exact resulting-main bytes: ' + path)

        def tampered_release_projection():
            path = 'site/app/settings/database_schemas.php'
            target = fixture / path
            original = target.read_bytes()
            try:
                tampered = original + b'\n# unreviewed direct projection source\n'
                target.write_bytes(tampered)
                proof.project_release_127_source(fixture, path, tampered)
            finally:
                target.write_bytes(original)

        refuses('REL-01 direct projection refuses unreviewed live source bytes',
                tampered_release_projection)
        proof.validate_ux09_source(fixture)
        passed('Direct UX-09 source validation accepts the exact REL-01 successor')

        for path in (
            *release_overlap_paths,
            'site/app/staff/accounts.php',
            'tests/browser_acceptance.py',
        ):
            projected = proof.project_mail_reply_forward_source(
                fixture, path, proof.ordinary(fixture, path))
            assert projected == proof.git(
                fixture, 'cat-file', 'blob', proof.MAIL_REPLY_FORWARD_BASE + ':' + path)
            passed('Reply/Forward shared projection restores exact resulting-main bytes: ' + path)

        def altered_receipt(change):
            def rewrite(target):
                data = json.loads(target.read_bytes())
                change(data)
                target.write_text(json.dumps(data))
            modified(proof.RECEIPT, rewrite)

        refuses('Refuses a forged component commit in the actual receipt',
                lambda: altered_receipt(lambda data: data['components']['software'].__setitem__('commit', '0' * 40)))
        refuses('Refuses a missing component in the actual receipt',
                lambda: altered_receipt(lambda data: data['components'].pop('finance')))
        refuses('Refuses an expanded comparison adapter scope',
                lambda: altered_receipt(lambda data: data['adapters'].__setitem__('tests/unreviewed.py', {})))
        refuses('Refuses an omitted reversible adapter edit',
                lambda: altered_receipt(lambda data: data['adapters']['tests/tickets_integration_contract.py']['edits'].pop()))

        def forged_adapter_and_receipt():
            adapter = fixture / 'tests/tickets_integration_contract.py'
            receipt = fixture / proof.RECEIPT
            old_adapter, old_receipt = adapter.read_bytes(), receipt.read_bytes()
            try:
                adapter.write_bytes(old_adapter + b'\n# different comparison logic\n')
                data = json.loads(old_receipt)
                data['adapters']['tests/tickets_integration_contract.py']['current_sha256'] = proof.digest(adapter.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                adapter.write_bytes(old_adapter)
                receipt.write_bytes(old_receipt)

        refuses('Refuses simultaneous comparison code and receipt-hash substitution', forged_adapter_and_receipt)

        for path in sorted(proof.REPAIRS):
            refuses('Refuses altered exact integration repair ' + path,
                    lambda path=path: modified(path, lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed repair\n')))
        refuses('Refuses a changed executable mode on an integration repair',
                lambda: executable_mode('tests/installed_owner_authority.php'))
        for path in ('tests/provider_force_ssl_controls_cases.py', 'tests/provider_directory_listing_controls_cases.py'):
            refuses('Refuses replacing the original recovery actor with the setup owner in ' + path,
                    lambda path=path: modified(path, lambda target: target.write_bytes(
                        target.read_bytes().replace(b'actor_session=owner, actor_id=', b'actor_session=setup, actor_id=', 1))))
        for group in ('browser', 'final'):
            def omit_required(target, group=group):
                inventory = json.loads(target.read_bytes())
                inventory[group].remove('software-commerce-native-authority.json')
                target.write_text(json.dumps(inventory))
            refuses('Refuses dropping required software authority evidence from ' + group,
                    lambda: modified('tools/acceptance-reports.json', omit_required))
        refuses('Refuses substituting the failed acceptance checkpoint',
                lambda: altered_receipt(lambda data: data.__setitem__('repair_base', '0' * 40)))
        refuses('Refuses omitted exact integration repair admission',
                lambda: altered_receipt(lambda data: data['integration_repairs'].pop('tests/installed_owner_authority.php')))
        refuses('Refuses omitted integration repair inverse',
                lambda: altered_receipt(lambda data: data['integration_repairs']['tests/provider_force_ssl_controls_cases.py']['edits'].pop()))
        refuses('Refuses omitted finance-readiness repair admission',
                lambda: altered_receipt(lambda data: data['integration_repairs'].pop('tests/finance_initial_enrollment_native.php')))
        refuses('Refuses omitted finance-readiness repair inverse',
                lambda: altered_receipt(lambda data: data['integration_repairs']['tests/finance_initial_enrollment_native.php']['edits'].pop()))
        refuses('Refuses omitted cloud intake account-proof successor inverse',
                lambda: altered_receipt(lambda data: data['integration_repairs']['tests/account_notification_workflow_contract.py']['edits'].pop()))
        refuses('Refuses omitted cloud intake acceptance-projection successor inverse',
                lambda: altered_receipt(lambda data: data['integration_repairs']['tests/acceptance_artifacts_test.py']['edits'].pop()))
        refuses('Refuses omitted cloud intake browser-fixture successor inverse',
                lambda: altered_receipt(lambda data: data['integration_repairs']['tests/provider_cloud_site_requests_cases.py']['edits'].pop()))
        refuses('Refuses omitted cloud intake fixture-identity successor inverse',
                lambda: altered_receipt(lambda data: data['integration_repairs']['tools/acceptance-contract.json']['edits'].pop()))

        def forged_repair_and_receipt():
            repair = fixture / 'tests/installed_owner_authority.py'
            receipt = fixture / proof.RECEIPT
            old_repair, old_receipt = repair.read_bytes(), receipt.read_bytes()
            try:
                repair.write_bytes(old_repair + b'\n# changed selected actor\n')
                data = json.loads(old_receipt)
                data['integration_repairs']['tests/installed_owner_authority.py']['current_sha256'] = proof.digest(repair.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                repair.write_bytes(old_repair)
                receipt.write_bytes(old_receipt)
        refuses('Refuses simultaneous runtime fixture code and repair-receipt hash substitution', forged_repair_and_receipt)

        from hosting_billing_options_wiring import (
            PREDECESSORS, RUNNER, BRIDGE, CASES, predecessor_sources, source as billing_source)
        billing_paths = set(PREDECESSORS) | {RUNNER, BRIDGE, CASES}
        billing_before = {path: proof.ordinary(fixture, path) for path in billing_paths}
        predecessors = predecessor_sources(fixture)
        assert set(predecessors) == set(PREDECESSORS)
        for path, expected in PREDECESSORS.items():
            assert predecessors[path] == proof.git(root, 'cat-file', 'blob', proof.REPAIR_BASE + ':' + path)
            assert proof.digest(predecessors[path]) == expected
            assert predecessors[path] != billing_before[path]
            passed('Billing predecessor comparison retains its original fixed bytes: ' + path)
        billing_projected = {
            path: proof.project_ux08_payment(fixture, path, billing_before[path]).replace(b'\r\n', b'\n')
            for path in (RUNNER, BRIDGE, CASES)
        }
        for path, projected in billing_projected.items():
            expected = proof.git(root, 'cat-file', 'blob', proof.UX08_PAYMENT_BASE + ':' + path)
            assert projected == expected.replace(b'\r\n', b'\n')
            assert projected != billing_before[path].replace(b'\r\n', b'\n')
            passed('Billing runtime projection retains its exact payment predecessor: ' + path)
        assert all(billing_source(fixture, path) == billing_before[path].replace(b'\r\n', b'\n')
                   for path in (RUNNER, BRIDGE, CASES))
        passed('Billing source reader retains the actual current runner, bridge and validators')
        assert all(proof.ordinary(fixture, path) == raw for path, raw in billing_before.items())
        passed('Billing historical comparison leaves every current callback and runtime input unchanged')

        def changed_payment_runner():
            runner = fixture / RUNNER
            original = runner.read_bytes()
            try:
                runner.write_bytes(original + b'\n# unreviewed payment runner change\n')
                proof.project_ux08_payment(fixture, RUNNER, proof.ordinary(fixture, RUNNER))
            finally:
                runner.write_bytes(original)
        refuses('Payment predecessor projection refuses a changed current runner', changed_payment_runner)

        from hosting_billing_options_wiring import (
            OLD_CHECKS_SHA256, RESULT_CHECK, portable_check_dump)
        historical_runner = proof.git(root, 'cat-file', 'blob', proof.REPAIR_BASE + ':' + RUNNER)
        assert proof.digest(historical_runner) == '823c245252be66f5286bb59edabfecddcae00f2b38a38088858b08d7d18a25e3'

        def runner_checks(raw):
            return [node for node in ast.walk(ast.parse(raw))
                    if isinstance(node, ast.Call) and isinstance(node.func, ast.Name)
                    and node.func.id == 'check' and not (node.args and isinstance(node.args[0], ast.Constant)
                                                        and node.args[0].value == RESULT_CHECK)]

        old_nodes, current_nodes = runner_checks(historical_runner), runner_checks(billing_projected[RUNNER])
        snapshots = [ast.dump(node, include_attributes=True) for node in current_nodes]
        assert [ast.dump(node, include_attributes=False) for node in old_nodes] == [
            ast.dump(node, include_attributes=False) for node in current_nodes]
        passed('All twelve current runner check bodies and their original traversal order equal the exact retained runner')

        def require_original_runner_checks(nodes):
            values = [portable_check_dump(node) for node in nodes]
            assert len(values) == 12 and proof.digest(json.dumps(values, separators=(',', ':')).encode()) == OLD_CHECKS_SHA256

        require_original_runner_checks(old_nodes)
        require_original_runner_checks(current_nodes)
        assert snapshots == [ast.dump(node, include_attributes=True) for node in current_nodes]
        passed('Portable serialization retains the original fixed checksum and leaves the current runner AST untouched')
        altered = copy.deepcopy(current_nodes)
        altered[0].args[1] = ast.Constant(value=True)
        refuses('Original runner checksum refuses a changed check body', lambda: require_original_runner_checks(altered))
        reordered = list(current_nodes)
        reordered[0], reordered[1] = reordered[1], reordered[0]
        refuses('Original runner checksum refuses reordered checks', lambda: require_original_runner_checks(reordered))
        refuses('Original runner checksum refuses an omitted check', lambda: require_original_runner_checks(current_nodes[:-1]))
        refuses('Original runner checksum refuses a duplicate additional check', lambda: require_original_runner_checks(current_nodes + [current_nodes[-1]]))
        keywords = copy.deepcopy(current_nodes)
        keywords[0].keywords.append(ast.keyword(arg='unreviewed', value=ast.Constant(value=True)))
        refuses('Portable serialization retains and refuses nonempty keyword changes', lambda: require_original_runner_checks(keywords))
        conditions = copy.deepcopy(current_nodes)
        comprehension = next(node for call in conditions for node in ast.walk(call)
                             if isinstance(node, ast.comprehension) and not node.ifs)
        comprehension.ifs.append(ast.Constant(value=True))
        refuses('Portable serialization retains and refuses nonempty comprehension conditions', lambda: require_original_runner_checks(conditions))
        arguments = copy.deepcopy(current_nodes)
        empty_call = next(node for call in arguments for node in ast.walk(call)
                          if isinstance(node, ast.Call) and not node.args)
        empty_call.args.append(ast.Constant(value='unreviewed'))
        refuses('Portable serialization retains and refuses nonempty positional arguments', lambda: require_original_runner_checks(arguments))

        def changed_billing_input(path, operation):
            target = fixture / path
            original = target.read_bytes()
            permission = target.stat().st_mode & 0o777
            try:
                operation(target)
                # Call the real boundary directly: no outer verify_current may
                # mask a missing admission check inside predecessor_sources.
                predecessor_sources(fixture)
            finally:
                target.write_bytes(original)
                target.chmod(permission)

        for path in sorted(billing_paths):
            refuses('Billing comparison refuses altered actual current source: ' + path,
                    lambda path=path: changed_billing_input(
                        path, lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed billing source\n')))
        refuses('Billing comparison refuses altered predecessor executable mode',
                lambda: executable_mode(next(iter(PREDECESSORS)),
                                        lambda: predecessor_sources(fixture)))

        def forged_billing_receipt(target):
            data = json.loads(target.read_bytes())
            data['integration_repairs'][next(iter(PREDECESSORS))]['current_sha256'] = '0' * 64
            target.write_text(json.dumps(data))
        refuses('Billing comparison refuses forged current predecessor admission',
                lambda: changed_billing_input(proof.RECEIPT, forged_billing_receipt))

        # Invoke the backup boundary itself after changing real current inputs;
        # do not let modified()'s own verification manufacture a refusal.
        from backups_acceptance_wiring import predecessor_browser as backup_predecessor
        browser_path = 'tests/browser_acceptance.py'
        contract_path = 'tools/acceptance-contract.json'
        before_browser = proof.ordinary(fixture, browser_path)
        before_contract = proof.ordinary(fixture, contract_path)
        current_contract = json.loads(before_contract)
        retained_browser = backup_predecessor(fixture, current_contract).encode()
        backup_hook = current_contract['backups_browser_extension']['hook'].encode()
        contact_hook = b'            from contacts_cases import run_browser as contacts_browser\n            contacts_browser(globals(),browser)\n'
        expected_browser = proof.git(fixture, 'cat-file', 'blob', proof.REPAIR_BASE + ':' + browser_path).replace(backup_hook, b'', 1)
        proof.require(retained_browser == expected_browser
                      and proof.digest(retained_browser) == current_contract['unchanged_test_sha256'][browser_path]
                      and before_browser.count(contact_hook) == 1
                      and proof.ordinary(fixture, browser_path) == before_browser
                      and proof.ordinary(fixture, contract_path) == before_contract,
                      'Backup comparison did not retain the exact predecessor and current runtime inputs')
        passed('Historical backup comparison preserves its exact guards and current contact browser continuation')

        def changed_backup_input(path, transform):
            target = fixture / path
            original = target.read_bytes()
            try:
                target.write_bytes(transform(original))
                backup_predecessor(fixture, json.loads(proof.ordinary(fixture, contract_path)))
            finally:
                target.write_bytes(original)

        refuses('Backup comparison refuses altered actual current browser source',
                lambda: changed_backup_input(browser_path, lambda raw: raw + b'\n# unreviewed browser continuation\n'))
        def wrong_predecessor(raw):
            value = json.loads(raw)
            value['unchanged_test_sha256'][browser_path] = '0' * 64
            return (json.dumps(value, indent=2) + '\n').encode()
        refuses('Backup comparison refuses altered actual current predecessor contract',
                lambda: changed_backup_input(contract_path, wrong_predecessor))
        def missing_backup_guard(raw):
            value = json.loads(raw)
            del value['backups_browser_extension']
            return (json.dumps(value, indent=2) + '\n').encode()
        refuses('Backup comparison refuses removal of its actual current contract guards',
                lambda: changed_backup_input(contract_path, missing_backup_guard))
        forged_contract = json.loads(before_contract)
        forged_contract.pop('backups_browser_extension')
        refuses('Backup comparison refuses a caller contract that omits the retained guards',
                lambda: backup_predecessor(fixture, forged_contract))

        for path in sorted(proof.ADDITIONS):
            refuses('Refuses altered exact contact addition ' + path,
                    lambda path=path: modified(path, lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed contact source\n')))
            refuses('Refuses missing exact contact addition ' + path,
                    lambda path=path: modified(path, lambda target: target.unlink()))
        refuses('Refuses an executable mode on a contact addition',
                lambda: executable_mode('site/app/clients/contacts.php'))
        refuses('Refuses a symlink replacing a contact addition',
                lambda: modified('site/app/clients/contacts.php', symlink))
        refuses('Refuses substituting the retained contact source commit',
                lambda: altered_receipt(lambda data: data.__setitem__('addition_source', '0' * 40)))
        refuses('Refuses substituting the retained contact source tree',
                lambda: altered_receipt(lambda data: data.__setitem__('addition_source_tree', '0' * 40)))
        refuses('Refuses an omitted contact addition admission',
                lambda: altered_receipt(lambda data: data['additions'].pop('site/app/clients/contacts.php')))
        refuses('Refuses an expanded contact addition scope',
                lambda: altered_receipt(lambda data: data['additions'].__setitem__('site/unreviewed-contact.php', {})))
        refuses('Refuses an omitted correction inverse for changed contact source',
                lambda: altered_receipt(lambda data: data['additions']['site/app/clients/contacts.php'].__setitem__('edits', [])))
        refuses('Refuses an empty-context contact correction inverse',
                lambda: altered_receipt(lambda data: data['additions']['site/app/clients/contacts.php']['edits'][0].__setitem__(0, '')))

        def forged_addition_and_receipt():
            addition = fixture / 'site/app/clients/contacts.php'
            receipt = fixture / proof.RECEIPT
            old_addition, old_receipt = addition.read_bytes(), receipt.read_bytes()
            try:
                addition.write_bytes(old_addition + b'\n# changed contact authority\n')
                data = json.loads(old_receipt)
                data['additions']['site/app/clients/contacts.php']['current_sha256'] = proof.digest(addition.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                addition.write_bytes(old_addition)
                receipt.write_bytes(old_receipt)

        refuses('Refuses simultaneous contact source and addition-receipt hash substitution', forged_addition_and_receipt)

        ticket_helper = 'tests/ticket_screenshot.py'
        refuses('Refuses altered exact ticket screenshot addition',
                lambda: modified(ticket_helper, lambda target: target.write_bytes(
                    target.read_bytes() + b'\n# unreviewed ticket capture\n')))
        refuses('Refuses missing exact ticket screenshot addition',
                lambda: modified(ticket_helper, lambda target: target.unlink()))
        refuses('Refuses an executable mode on the ticket screenshot addition',
                lambda: executable_mode(ticket_helper))
        refuses('Refuses a symlink replacing the ticket screenshot addition',
                lambda: modified(ticket_helper, symlink))
        refuses('Refuses substituting the retained ticket source commit',
                lambda: altered_receipt(lambda data: data.__setitem__('ticket_addition_source', '0' * 40)))
        refuses('Refuses substituting the retained ticket source tree',
                lambda: altered_receipt(lambda data: data.__setitem__('ticket_addition_source_tree', '0' * 40)))
        refuses('Refuses an omitted ticket addition admission',
                lambda: altered_receipt(lambda data: data['ticket_additions'].pop(ticket_helper)))
        refuses('Refuses an expanded ticket addition scope',
                lambda: altered_receipt(lambda data: data['ticket_additions'].__setitem__(
                    'tests/unreviewed-ticket-screenshot.py', {'sha256': '0' * 64, 'mode': '100644'})))

        def forged_ticket_addition_and_receipt():
            addition = fixture / ticket_helper
            receipt = fixture / proof.RECEIPT
            old_addition, old_receipt = addition.read_bytes(), receipt.read_bytes()
            try:
                addition.write_bytes(old_addition + b'\n# changed ticket screenshot helper\n')
                data = json.loads(old_receipt)
                data['ticket_additions'][ticket_helper]['sha256'] = proof.digest(addition.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                addition.write_bytes(old_addition)
                receipt.write_bytes(old_receipt)

        refuses('Refuses simultaneous ticket helper and addition-receipt hash substitution',
                forged_ticket_addition_and_receipt)

        diagnostic_path = 'tests/diagnostic_registry.py'
        for path in sorted(proof.DIAGNOSTIC_ADDITIONS):
            refuses('Refuses changed exact diagnostic addition ' + path,
                    lambda path=path: modified(path, lambda target: target.write_bytes(
                        target.read_bytes() + b'\n# unreviewed diagnostic change\n')))
            refuses('Refuses missing exact diagnostic addition ' + path,
                    lambda path=path: modified(path, lambda target: target.unlink()))
        refuses('Refuses an executable mode on a diagnostic addition',
                lambda: executable_mode(diagnostic_path))
        refuses('Refuses a symlink replacing a diagnostic addition',
                lambda: modified(diagnostic_path, symlink))
        refuses('Refuses substituting the retained diagnostic source commit',
                lambda: altered_receipt(lambda data: data.__setitem__(
                    'diagnostic_addition_source', '0' * 40)))
        refuses('Refuses substituting the retained diagnostic source tree',
                lambda: altered_receipt(lambda data: data.__setitem__(
                    'diagnostic_addition_source_tree', '0' * 40)))
        refuses('Refuses an unknown diagnostic addition',
                lambda: altered_receipt(lambda data: data['diagnostic_additions'].__setitem__(
                    'tests/unreviewed-diagnostic.py', {
                        'source_sha256': '0' * 64, 'current_sha256': '0' * 64,
                        'delta_sha256': '0' * 64, 'mode': '100644'})))

        def overlap_diagnostic_with_repair(data):
            row = data['diagnostic_additions'].pop('.github/workflows/diagnostic-tests.yml')
            data['diagnostic_additions']['.github/workflows/application-tests.yml'] = row
        refuses('Refuses diagnostic addition overlap with an existing repair path',
                lambda: altered_receipt(overlap_diagnostic_with_repair))
        refuses('Refuses a substituted diagnostic delta identity',
                lambda: altered_receipt(lambda data: data['diagnostic_additions'][diagnostic_path].__setitem__(
                    'delta_sha256', '0' * 64)))

        def forged_diagnostic_and_receipt():
            addition = fixture / diagnostic_path
            receipt = fixture / proof.RECEIPT
            old_addition, old_receipt = addition.read_bytes(), receipt.read_bytes()
            try:
                addition.write_bytes(old_addition + b'\n# changed diagnostic registry\n')
                data = json.loads(old_receipt)
                data['diagnostic_additions'][diagnostic_path]['current_sha256'] = proof.digest(addition.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                addition.write_bytes(old_addition)
                receipt.write_bytes(old_receipt)
        refuses('Refuses simultaneous diagnostic source and current-hash substitution',
                forged_diagnostic_and_receipt)

        def altered_resulting_main_diagnostic_identity(attribute):
            original = getattr(proof, attribute)
            try:
                setattr(proof, attribute, '0' * 40)
                proof.validate_resulting_main_diagnostic_mail_reply_source(fixture)
            finally:
                setattr(proof, attribute, original)

        for attribute, label in (
            ('RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE', 'base'),
            ('RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE_TREE', 'base tree'),
            ('RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE', 'source'),
            ('RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE_TREE', 'source tree'),
        ):
            refuses('Refuses a substituted resulting-main diagnostic ' + label,
                    lambda attribute=attribute: altered_resulting_main_diagnostic_identity(attribute))

        database_addition = 'tests/database_schema_registry_policy.php'
        for path in sorted(proof.DATABASE_WORKFLOW_ADDITIONS):
            refuses('Refuses changed exact database workflow addition ' + path,
                    lambda path=path: modified(path, lambda target: target.write_bytes(
                        target.read_bytes() + b'\n# unreviewed database workflow change\n')))
            refuses('Refuses missing exact database workflow addition ' + path,
                    lambda path=path: modified(path, lambda target: target.unlink()))
        refuses('Refuses an executable mode on a database workflow addition',
                lambda: executable_mode(database_addition))
        refuses('Refuses a symlink replacing a database workflow addition',
                lambda: modified(database_addition, symlink))
        refuses('Refuses substituting the exact database workflow base',
                lambda: altered_receipt(lambda data: data.__setitem__(
                    'database_workflow_base', '0' * 40)))
        refuses('Refuses substituting the exact database workflow base tree',
                lambda: altered_receipt(lambda data: data.__setitem__(
                    'database_workflow_base_tree', '0' * 40)))
        refuses('Refuses substituting the exact database workflow source',
                lambda: altered_receipt(lambda data: data.__setitem__(
                    'database_workflow_source', '0' * 40)))
        refuses('Refuses substituting the exact database workflow source tree',
                lambda: altered_receipt(lambda data: data.__setitem__(
                    'database_workflow_source_tree', '0' * 40)))
        refuses('Refuses an omitted database workflow source path',
                lambda: altered_receipt(lambda data: data['database_workflow_source_paths'].remove(
                    database_addition)))
        refuses('Refuses an expanded database workflow source path set',
                lambda: altered_receipt(lambda data: data['database_workflow_source_paths'].append(
                    'tests/unreviewed-database-workflow.php')))
        refuses('Refuses an omitted database workflow addition admission',
                lambda: altered_receipt(lambda data: data['database_workflow_additions'].pop(
                    database_addition)))
        refuses('Refuses a database workflow addition overlapping an integration repair',
                lambda: altered_receipt(lambda data: data['database_workflow_additions'].__setitem__(
                    '.github/workflows/application-tests.yml',
                    {'sha256': proof.digest(proof.ordinary(fixture, '.github/workflows/application-tests.yml')),
                     'mode': '100644'})))
        refuses('Refuses a substituted database workflow addition hash',
                lambda: altered_receipt(lambda data: data['database_workflow_additions'][database_addition].__setitem__(
                    'sha256', '0' * 64)))

        def forged_database_addition_and_receipt():
            addition = fixture / database_addition
            receipt = fixture / proof.RECEIPT
            old_addition, old_receipt = addition.read_bytes(), receipt.read_bytes()
            try:
                addition.write_bytes(old_addition + b'\n# forged database workflow source\n')
                data = json.loads(old_receipt)
                data['database_workflow_additions'][database_addition]['sha256'] = proof.digest(addition.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                addition.write_bytes(old_addition)
                receipt.write_bytes(old_receipt)

        refuses('Refuses simultaneous database workflow source and receipt-hash substitution',
                forged_database_addition_and_receipt)

        def altered_database_hydration_identity():
            original = proof.DATABASE_WORKFLOW_SOURCE_TREE
            try:
                proof.DATABASE_WORKFLOW_SOURCE_TREE = '0' * 40
                proof.hydration_origin(fixture, proof.git(fixture, 'rev-parse', 'HEAD').decode().strip())
            finally:
                proof.DATABASE_WORKFLOW_SOURCE_TREE = original

        refuses('Refuses altered database workflow identity in the authorized hydration history',
                altered_database_hydration_identity)

        cloud_addition = 'tests/cloud_site_intake_contract.php'
        for path in sorted(proof.CLOUD_INTAKE_ADDITIONS):
            refuses('Refuses changed exact cloud intake addition ' + path,
                    lambda path=path: modified(path, lambda target: target.write_bytes(
                        target.read_bytes() + b'\n# unreviewed cloud intake change\n')))
            refuses('Refuses missing exact cloud intake addition ' + path,
                    lambda path=path: modified(path, lambda target: target.unlink()))
        refuses('Refuses an executable mode on a cloud intake addition',
                lambda: executable_mode(cloud_addition))
        refuses('Refuses a symlink replacing a cloud intake addition',
                lambda: modified(cloud_addition, symlink))
        refuses('Refuses substituting the exact cloud intake base',
                lambda: altered_receipt(lambda data: data.__setitem__('cloud_intake_base', '0' * 40)))
        refuses('Refuses substituting the exact cloud intake base tree',
                lambda: altered_receipt(lambda data: data.__setitem__('cloud_intake_base_tree', '0' * 40)))
        refuses('Refuses substituting the exact cloud intake source',
                lambda: altered_receipt(lambda data: data.__setitem__('cloud_intake_source', '0' * 40)))
        refuses('Refuses substituting the exact cloud intake source tree',
                lambda: altered_receipt(lambda data: data.__setitem__('cloud_intake_source_tree', '0' * 40)))
        refuses('Refuses an omitted cloud intake source path',
                lambda: altered_receipt(lambda data: data['cloud_intake_source_paths'].remove(cloud_addition)))
        refuses('Refuses an expanded cloud intake source path set',
                lambda: altered_receipt(lambda data: data['cloud_intake_source_paths'].append(
                    'tests/unreviewed-cloud-intake.php')))
        refuses('Refuses an omitted cloud intake addition admission',
                lambda: altered_receipt(lambda data: data['cloud_intake_additions'].pop(cloud_addition)))
        refuses('Refuses a cloud intake addition overlapping an integration repair',
                lambda: altered_receipt(lambda data: data['cloud_intake_additions'].__setitem__(
                    '.github/workflows/application-tests.yml',
                    {'sha256': proof.digest(proof.ordinary(fixture, '.github/workflows/application-tests.yml')),
                     'mode': '100644'})))
        refuses('Refuses a substituted cloud intake addition hash',
                lambda: altered_receipt(lambda data: data['cloud_intake_additions'][cloud_addition].__setitem__(
                    'sha256', '0' * 64)))

        def forged_cloud_addition_and_receipt():
            addition = fixture / cloud_addition
            receipt = fixture / proof.RECEIPT
            old_addition, old_receipt = addition.read_bytes(), receipt.read_bytes()
            try:
                addition.write_bytes(old_addition + b'\n# forged cloud intake source\n')
                data = json.loads(old_receipt)
                data['cloud_intake_additions'][cloud_addition]['sha256'] = proof.digest(addition.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                addition.write_bytes(old_addition)
                receipt.write_bytes(old_receipt)

        refuses('Refuses simultaneous cloud intake source and receipt-hash substitution',
                forged_cloud_addition_and_receipt)

        def altered_cloud_hydration_identity():
            original = proof.CLOUD_INTAKE_SOURCE_TREE
            try:
                proof.CLOUD_INTAKE_SOURCE_TREE = '0' * 40
                proof.hydration_origin(fixture, proof.git(fixture, 'rev-parse', 'HEAD').decode().strip())
            finally:
                proof.CLOUD_INTAKE_SOURCE_TREE = original

        refuses('Refuses altered cloud intake identity in the authorized hydration history',
                altered_cloud_hydration_identity)

        source = fixture / 'tests/software_authority_provenance.py'
        cached = Path(importlib.util.cache_from_source(str(source)))
        py_compile.compile(str(source), cfile=str(cached), doraise=True)
        proof.verify_current(fixture)
        passed('Ordinary generated test cache does not become a Git/source/package input')
        subprocess.run(['git', 'add', '--force', str(cached.relative_to(fixture))], cwd=fixture, check=True)
        try:
            refuses('Tracked bytecode cannot use the generated-cache exclusion', lambda: proof.verify_current(fixture))
        finally:
            subprocess.run(['git', 'rm', '--quiet', '--cached', str(cached.relative_to(fixture))], cwd=fixture, check=True)
        refuses('Retained source drift is refused even when an ordinary generated cache exists',
                lambda: modified('tests/software_authority_provenance.py',
                                 lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed source\n')))

        refuses('Historical projection refuses unrelated comparison input', lambda: proof.account_call(
            fixture, 'provider_browser_fixture_provenance', 'predecessor',
            path='tests/release_cases.py', value=b'unreviewed predecessor'))
        refuses('Historical projection cannot dispatch a runtime module', lambda: proof.account_call(
            fixture, 'software_commerce_native', 'run'))
        refuses('Historical projection never returns shipping application bytes', lambda: proof.account_call(
            fixture, 'provider_browser_fixture_provenance', 'source', path='site/app/billing/software_client.php'))
        refuses('The finite report-inventory comparison does not admit other tools', lambda: proof.account_call(
            fixture, 'provider_browser_fixture_provenance', 'source', path='tools/build.py'))
        refuses('The finite report-inventory comparison cannot supply runtime input', lambda: proof.account_call(
            fixture, 'provider_browser_fixture_provenance', 'predecessor', path='tools/acceptance-reports.json',
            value=proof.ordinary(fixture, 'tools/acceptance-reports.json')))
        historical_reports = proof.account_call(fixture, 'provider_browser_fixture_provenance', 'source',
                                               path='tools/acceptance-reports.json')
        assert historical_reports == proof.git(root, 'cat-file', 'blob', proof.REPAIR_BASE + ':tools/acceptance-reports.json')
        assert historical_reports != proof.ordinary(fixture, 'tools/acceptance-reports.json')
        passed('Historical report comparison is exact while runtime retains the new required evidence')
        payment_path = 'tests/payments_cases.py'
        current_payment = proof.ordinary(fixture, payment_path)
        historical_payment = proof.account_call(
            fixture, 'account_notification_fixture_provenance', 'core_input',
            path=payment_path, value=current_payment)
        assert historical_payment == proof.git(root, 'cat-file', 'blob', proof.REPAIR_BASE + ':' + payment_path)
        assert historical_payment == proof.git(root, 'cat-file', 'blob', proof.COMPONENTS['account']['commit'] + ':' + payment_path)
        assert historical_payment != current_payment
        passed('Core browser comparison uses the exact receipted payment predecessor while runtime keeps current privacy checks')
        refuses('Core browser comparison refuses unrelated current payment input', lambda: proof.account_call(
            fixture, 'account_notification_fixture_provenance', 'core_input',
            path=payment_path, value=current_payment + b'\n# unreviewed payment comparison\n'))
        refuses('Historical projection cannot dispatch an arbitrary callback', lambda: proof.projected_validation(
            fixture, lambda root: [], ('docs/STANDALONE_ACCEPTANCE_REPAIRS.json',)))
        proof.verify_current(fixture)
        passed('Restored actual filesystem passes after every rejection case')

    return {'passed': True, 'count': len(checks), 'checks': checks,
            'scope': 'Actual source/receipt/mode/symlink mutation boundaries and ordinary generated cache compatibility. No native SQL or browser execution.'}


def shallow_acceptance(root=ROOT):
    """Use the finite protected-workflow repair in a genuine depth-one clone."""
    checks = []

    def check(name, condition):
        proof.require(condition, name)
        checks.append({'name': name, 'passed': True})

    output = root / 'qa/combined-composition/shallow-corrected'
    output.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='cwp-composition-shallow-') as directory:
        fixture = Path(directory) / 'source'
        subprocess.run(['git', 'clone', '--quiet', '--depth', '1', '--no-tags', root.as_uri(), str(fixture)], check=True)
        for path in (proof.ADAPTERS | proof.REPAIRS | proof.ADDITIONS | proof.TICKET_ADDITIONS
                     | proof.DIAGNOSTIC_ADDITIONS | proof.DATABASE_WORKFLOW_ADDITIONS
                     | proof.CLOUD_INTAKE_ADDITIONS
                     | {proof.VERIFIER, proof.RECEIPT, proof.REGRESSION, proof.INVENTORY,
                        'tools/acceptance-contract.json'}):
            target = fixture / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(proof.ordinary(root, path))
            target.chmod(0o644)
        before = proof.history_state(fixture)
        check('Fixture is an actual depth-one Git clone before the unchanged helper runs',
              proof.git(fixture, 'rev-parse', '--is-shallow-repository').strip() == b'true')
        missing = subprocess.run(['git', 'cat-file', '-e', proof.BASE + '^{tree}'], cwd=fixture, capture_output=True)
        check('Required historical component ancestry is genuinely absent before hydration', missing.returncode != 0)
        workflow_path = '.github/workflows/application-tests.yml'
        current_workflow = proof.ordinary(fixture, workflow_path)
        predecessor_workflow = proof.reconstruct_repair(
            json.loads(proof.ordinary(fixture, proof.RECEIPT)), workflow_path, current_workflow)
        check('Protected complete application workflow retains its original exact bytes through the finite repair',
              predecessor_workflow
              == proof.git(root, 'cat-file', 'blob', proof.REPAIR_BASE + ':' + workflow_path)
              == proof.git(root, 'cat-file', 'blob', proof.BASE + ':' + workflow_path))
        command = [sys.executable, '-B', 'tests/acceptance_artifacts_test.py', '--report', str(output / 'helpers.json')]
        with (output / 'stdout.log').open('wb') as stdout, (output / 'stderr.log').open('wb') as stderr:
            execution = subprocess.run(command, cwd=fixture, stdout=stdout, stderr=stderr)
        after = proof.history_state(fixture)
        record = {'command': command, 'exit_code': execution.returncode, 'before': before, 'after': after,
                  'protected_workflow_sha256': proof.digest(current_workflow),
                  'protected_workflow_predecessor_sha256': proof.digest(predecessor_workflow)}
        (output / 'execution.json').write_text(json.dumps(record, indent=2) + '\n')
        check('Unchanged maintained acceptance helper succeeds from genuine shallow source', execution.returncode == 0)
        check('The unchanged helper has no source-proof stderr', not (output / 'stderr.log').read_bytes())
        report = json.loads((output / 'helpers.json').read_bytes())
        check('Every retained helper assertion remains passing',
              report['passed'] is True and bool(report['checks']) and all(row['passed'] is True for row in report['checks']))
        check('Only shallow/object metadata changed; HEAD, refs, index and actual source stayed exact', before == after)
        check('Exact current HEAD fetch made the required ancestry available',
              proof.git(fixture, 'rev-parse', '--is-shallow-repository').strip() == b'false')
        for identity in proof.COMPONENTS.values():
            proof.git(fixture, 'merge-base', '--is-ancestor', identity['commit'], 'HEAD')
        check('All four exact component ancestry requirements still execute', True)
        proof.git(fixture, 'merge-base', '--is-ancestor', proof.ADDITION_SOURCE, 'HEAD')
        check('The exact retained contact source is also hydrated as required current ancestry', True)
        proof.git(fixture, 'merge-base', '--is-ancestor', proof.TICKET_ADDITION_SOURCE, 'HEAD')
        check('The exact retained ticket addition source is hydrated as required current ancestry',
              proof.git(fixture, 'rev-parse', proof.TICKET_ADDITION_SOURCE + '^{tree}').decode().strip()
              == proof.TICKET_ADDITION_SOURCE_TREE)
        proof.git(fixture, 'merge-base', '--is-ancestor', proof.DIAGNOSTIC_ADDITION_SOURCE, 'HEAD')
        check('The exact retained diagnostic addition source is hydrated as required current ancestry',
              proof.git(fixture, 'rev-parse', proof.DIAGNOSTIC_ADDITION_SOURCE + '^{tree}').decode().strip()
              == proof.DIAGNOSTIC_ADDITION_SOURCE_TREE)
        proof.git(fixture, 'merge-base', '--is-ancestor', proof.DATABASE_WORKFLOW_BASE,
                  proof.DATABASE_WORKFLOW_SOURCE)
        proof.git(fixture, 'merge-base', '--is-ancestor', proof.DATABASE_WORKFLOW_SOURCE, 'HEAD')
        check('The exact database workflow base and source are hydrated as required current ancestry',
              proof.git(fixture, 'rev-parse', proof.DATABASE_WORKFLOW_BASE + '^{tree}').decode().strip()
              == proof.DATABASE_WORKFLOW_BASE_TREE
              and proof.git(fixture, 'rev-parse', proof.DATABASE_WORKFLOW_SOURCE + '^{tree}').decode().strip()
              == proof.DATABASE_WORKFLOW_SOURCE_TREE)
        proof.git(fixture, 'merge-base', '--is-ancestor', proof.CLOUD_INTAKE_BASE,
                  proof.CLOUD_INTAKE_SOURCE)
        proof.git(fixture, 'merge-base', '--is-ancestor', proof.CLOUD_INTAKE_SOURCE, 'HEAD')
        check('The exact cloud intake base and source are hydrated as required current ancestry',
              proof.git(fixture, 'rev-parse', proof.CLOUD_INTAKE_BASE + '^{tree}').decode().strip()
              == proof.CLOUD_INTAKE_BASE_TREE
              and proof.git(fixture, 'rev-parse', proof.CLOUD_INTAKE_SOURCE + '^{tree}').decode().strip()
              == proof.CLOUD_INTAKE_SOURCE_TREE)
        check('A later source check performs no duplicate history fetch', proof.ensure_history(fixture) == {'performed': False})

        # A second genuine shallow clone proves the local test transport is not
        # an arbitrary upstream or environment bypass.
        rejected = Path(directory) / 'unreviewed-origin'
        subprocess.run(['git', 'clone', '--quiet', '--depth', '1', '--no-tags', root.as_uri(), str(rejected)], check=True)
        subprocess.run(['git', 'remote', 'set-url', 'origin', 'https://github.com/unreviewed/other.git'], cwd=rejected, check=True)
        refused = False
        try:
            proof.ensure_history(rejected)
        except AssertionError:
            refused = True
        check('An unrelated upstream is refused before any history fetch', refused)
        check('Refusing an unrelated upstream leaves the clone shallow',
              proof.git(rejected, 'rev-parse', '--is-shallow-repository').strip() == b'true')

    return {'passed': True, 'count': len(checks), 'checks': checks,
            'maintained_helper_checks': len(report['checks']),
            'scope': 'Genuine shallow Git and unchanged historical source helper only. No SQL/browser/merchant execution.'}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--shallow', action='store_true', help='Run the unchanged helper from a genuine depth-one clone.')
    arguments = parser.parse_args()
    print(json.dumps(shallow_acceptance() if arguments.shallow else run(), indent=2))
