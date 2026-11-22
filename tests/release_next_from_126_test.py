"""Offline refusal tests for the pinned published v1.26.1 release diagnostic."""
from pathlib import Path
from tempfile import TemporaryDirectory
import stat
import zipfile

from release_next_from_126 import archive_facts, baseline_version, candidate_version


def refuses(label, fn):
    try:
        fn()
    except (RuntimeError, KeyError, zipfile.BadZipFile):
        print('PASS: ' + label)
        return
    raise AssertionError(label)


def make_zip(path, entries):
    with zipfile.ZipFile(path, 'w') as archive:
        for name, data in entries:
            if isinstance(data, zipfile.ZipInfo):
                archive.writestr(data, b'x')
            else:
                archive.writestr(name, data)


def main():
    with TemporaryDirectory() as work:
        root = Path(work)
        (root / 'site/config').mkdir(parents=True)
        version = root / 'VERSION'
        defaults = root / 'site/config/defaults.php'
        version.write_text('1.27.0\n', encoding='utf-8')
        defaults.write_text("<?php\nconst CWP_VERSION = '1.27.0';\n", encoding='utf-8')
        assert candidate_version(root) == '1.27.0'
        print('PASS: matching candidate source and runtime version')
        defaults.write_text("<?php\nconst CWP_VERSION = '1.26.1';\n", encoding='utf-8')
        refuses('refuse stale PHP runtime version', lambda: candidate_version(root))
        defaults.write_text("<?php\nconst CWP_VERSION = '1.27.0';\n", encoding='utf-8')
        for invalid in ('1.26.1', '1.26.0', '0.99.0', '1.27.0-rc1', '1.027.0'):
            version.write_text(invalid + '\n', encoding='utf-8')
            refuses('refuse invalid or non-increasing candidate ' + invalid, lambda: candidate_version(root))
        version.write_text('1.27.0\n', encoding='utf-8')

        archive = root / 'archive.zip'
        make_zip(archive, [('config/defaults.php', "<?php\nconst CWP_VERSION = '1.26.1';\n")])
        assert archive_facts(archive)['members'] == 1
        baseline_version(archive)
        print('PASS: pinned version parsed from a valid published-layout ZIP')
        make_zip(archive, [('config/defaults.php', "<?php\nconst CWP_VERSION = '1.26.0';\n")])
        refuses('refuse mismatched published baseline version', lambda: baseline_version(archive))
        for bad in ('../escape.txt', '/absolute/file', 'C:/drive/file', 'nested//file', 'nested\\file'):
            make_zip(archive, [(bad, 'x')])
            refuses('refuse unsafe archive member ' + repr(bad), lambda: archive_facts(archive))
        make_zip(archive, [('File.txt', 'x'), ('file.TXT', 'y')])
        refuses('refuse case-colliding archive members', lambda: archive_facts(archive))
        symlink = zipfile.ZipInfo('symbolic')
        symlink.create_system = 3
        symlink.external_attr = (stat.S_IFLNK | 0o777) << 16
        make_zip(archive, [('symbolic', symlink)])
        refuses('refuse symlink archive member', lambda: archive_facts(archive))

    print('PASS: offline 1.26.1 release diagnostic guards')


if __name__ == '__main__':
    main()
