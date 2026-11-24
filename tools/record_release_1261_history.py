"""Record only the strictly verified published 1.26.1 inventory for the updater."""
from pathlib import Path
import argparse
import json
import os

import fetch_release_127_baseline as release


ROOT = Path(__file__).resolve().parents[1]


def main(archive: Path, manifest_path: Path, history_path: Path) -> None:
    manifest_data = manifest_path.read_bytes()
    files = release.validate_manifest_bytes(manifest_data)
    release.validate_archive_bytes(archive.read_bytes(), files)
    entry = {
        'archive_sha256': release.ARCHIVE_SHA256,
        'source_kind': 'published-release',
        'source_commit': release.SOURCE,
        'release_asset_id': release.ARCHIVE_ASSET_ID,
        'manifest_asset_id': release.MANIFEST_ASSET_ID,
        'manifest_sha256': release.MANIFEST_SHA256,
        'files': files,
    }
    history = json.loads(history_path.read_text(encoding='utf-8'))
    if not isinstance(history, dict) or not history:
        raise RuntimeError('Updater history is malformed')
    existing = history.get('1.26.1')
    if existing is not None and existing != entry:
        raise RuntimeError('Existing 1.26.1 updater inventory differs')
    if ((existing is None and list(history)[-1] != '1.26.0')
            or (existing is not None and list(history)[-2:] != ['1.26.0', '1.26.1'])):
        raise RuntimeError('Updater history release order differs')
    if existing is None:
        history['1.26.1'] = entry
        temporary = history_path.with_name(history_path.name + '.release127.tmp')
        if temporary.exists() or temporary.is_symlink():
            raise RuntimeError('History staging path already exists')
        try:
            with temporary.open('x', encoding='utf-8', newline='\n') as output:
                output.write(json.dumps(history, indent=2) + '\n')
            os.replace(temporary, history_path)
        finally:
            temporary.unlink(missing_ok=True)
    print(json.dumps({
        'recorded': True, 'version': '1.26.1', 'source': release.SOURCE,
        'archive_sha256': release.ARCHIVE_SHA256,
        'manifest_sha256': release.MANIFEST_SHA256, 'files': len(files),
    }))


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('archive', type=Path)
    parser.add_argument('manifest', type=Path)
    parser.add_argument('--history', type=Path,
                        default=ROOT / 'tools/legacy-release-files.json')
    args = parser.parse_args()
    main(args.archive, args.manifest, args.history)
