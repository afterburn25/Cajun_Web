"""Portable runner guards only: no SQL, provider, subprocess, or clock simulation."""
from copy import deepcopy
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

import hosting_catalog_purchase as purchase


class PurchaseGuards(unittest.TestCase):
    def setUp(self):
        self.process = patch.object(purchase.subprocess, "run", side_effect=AssertionError("No subprocess in portable guards"))
        self.process.start()
        self.addCleanup(self.process.stop)

    def report(self, phase="due"):
        offset = purchase.OFFSET_SECONDS if phase == "prepare" else 0
        return {"phase": phase, "passed": True, "simulated_application_clock": phase == "prepare",
                "clock": {"parent_epoch": 2000000000, "php_epoch": 2000000000-offset,
                          "database_epoch": 2000000000, "offset_seconds": offset},
                "checks": [{"name": str(i), "passed": True} for i in range(4)]}

    def accepted(self, value, phase="due"):
        return purchase.decode_report(json.dumps(value).encode(), phase)

    def test_prepare_only_child_gets_faketime(self):
        original = {"PATH": "safe", "CWP_TEST_DATABASE": "cwp_test"}
        parent = purchase.clean_environment(original)
        before = dict(parent)
        child = purchase.phase_environment(parent, "prepare", 2000000000)
        self.assertEqual(parent, before)
        self.assertNotIn("LD_PRELOAD", original)
        self.assertEqual(child["LD_PRELOAD"], str(purchase.LIBRARY))
        self.assertEqual(child["FAKETIME"], "-2d")
        self.assertEqual(child["FAKETIME_DONT_FAKE_MONOTONIC"], "1")
        for phase in purchase.PHASES[1:]:
            current = purchase.phase_environment(parent, phase, 2000000001)
            self.assertFalse(any(k.startswith("FAKETIME") or k == "LD_PRELOAD" for k in current))
            self.assertEqual(current["CWP_PURCHASE_PARENT_EPOCH"], "2000000001")

    def test_inherited_runtime_overrides_refused(self):
        for key in ["LD_PRELOAD", "LD_AUDIT", "FAKETIME", "FAKETIME_TIMESTAMP_FILE", "FAKETIME_SKIP_CMDS",
                    "FAKETIME_DONT_FAKE_MONOTONIC", "DONT_FAKE_MONOTONIC", "CWP_PURCHASE_PARENT_EPOCH",
                    "CWP_TEST_HOSTING_PURCHASE", "PHPRC", "PHP_INI_SCAN_DIR"]:
            with self.subTest(key=key), self.assertRaises(RuntimeError):
                purchase.clean_environment({key: ""})

    def test_unknown_phase_refused(self):
        for phase in ("", "prepare2", "../due", "all"):
            with self.subTest(phase=phase), self.assertRaises(RuntimeError):
                purchase.phase_environment({}, phase, 2000000000)

    def test_all_closed_report_phases(self):
        for phase in purchase.PHASES:
            self.assertEqual(self.accepted(self.report(phase), phase)["phase"], phase)

    def test_failure_or_old_report_refused(self):
        for key, value in [("passed", False), ("passed", 1), ("phase", "prepare"),
                           ("simulated_application_clock", 0), ("error", {"message": "failure"})]:
            report = self.report()
            report[key] = value
            with self.subTest(key=key, value=value), self.assertRaises(RuntimeError):
                self.accepted(report)

    def test_each_failed_named_check_refused(self):
        for value in (False, 1, "true", None):
            report = self.report()
            report["checks"][2]["passed"] = value
            with self.subTest(value=value), self.assertRaises(RuntimeError):
                self.accepted(report)

    def test_missing_duplicate_names_refused(self):
        for checks in ([], [{"name": "x", "passed": True}] * 4,
                       [{"name": "", "passed": True}] * 4):
            report = self.report()
            report["checks"] = checks
            with self.subTest(checks=checks), self.assertRaises(RuntimeError):
                self.accepted(report)

    def test_shifted_server_or_wrong_application_clock_refused(self):
        for phase, key, value in [("prepare", "database_epoch", 2000000000-172800),
                                  ("prepare", "php_epoch", 2000000000),
                                  ("due", "php_epoch", 2000000000-172800),
                                  ("due", "offset_seconds", False),
                                  ("due", "php_epoch", 2000000000.0)]:
            report = self.report(phase)
            report["clock"][key] = value
            with self.subTest(phase=phase, key=key), self.assertRaises(RuntimeError):
                self.accepted(report, phase)

    def test_duplicate_nonfinite_invalid_utf8_refused(self):
        for data in (b'{"phase":"due","phase":"due"}', b'{"value":NaN}', b'"\xff"'):
            with self.subTest(data=data), self.assertRaises((RuntimeError, ValueError, UnicodeError)):
                purchase.decode_report(data, "due")

    def test_existing_outputs_and_file_parents_refused(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary).resolve()
            path = purchase.contained_fresh(root, "qa", "hosting-purchase")
            self.assertFalse(path.exists())
            path.mkdir(parents=True)
            with self.assertRaises(RuntimeError):
                purchase.contained_fresh(root, "qa", "hosting-purchase")
            (root / "test-runtime").write_text("not a directory")
            with self.assertRaises(RuntimeError):
                purchase.contained_fresh(root, "test-runtime", "hosting-purchase")

    def test_symlink_parent_refused_without_following(self):
        # A mocked lstat result is portable on Windows without symlink privilege.
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary).resolve()
            actual = Path.is_symlink
            with patch.object(Path, "is_symlink", lambda p: p == root / "qa" or actual(p)):
                with self.assertRaises(RuntimeError):
                    purchase.contained_fresh(root, "qa", "hosting-purchase")

    def test_diagnostic_tail_redacts_fixed_secrets_and_paths(self):
        root = Path("/synthetic-checkout")
        raw = ("\n".join(purchase.SYNTHETIC_VALUES) + "\ncustom-db-password " + str(root) + "\n#0 stack").encode()
        safe = purchase.safe_tail(raw, root, "custom-db-password")
        self.assertNotIn("custom-db-password", safe)
        self.assertNotIn(str(root), safe)
        for secret in purchase.SYNTHETIC_VALUES:
            self.assertNotIn(secret, safe)
        self.assertNotIn("#0", safe)
        self.assertLessEqual(len(safe), 1200)


if __name__ == "__main__":
    unittest.main(verbosity=2)
