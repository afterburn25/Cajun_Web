"""Check this repository's continuity files and inline local Markdown links.

Uses Python's standard library only. External URLs are deliberately not fetched.
This is a documentation check, not an application or release acceptance test.
"""

from pathlib import Path
import re
import sys
import unicodedata
from urllib.parse import unquote, urlsplit


ROOT = Path(__file__).resolve().parents[1]
REQUIRED = (
    "AGENTS.md", "README.md", "PROJECT_STATUS.md", "SESSION_HANDOFF.md",
    "CHANGELOG.md", "docs/PROJECT_DIRECTION.md", "docs/ROADMAP.md",
    "docs/SESSION_LOG.md", "docs/DECISIONS.md", "docs/RELEASE_WORKFLOW.md",
    "docs/RELEASES.md", "docs/AGENT_COORDINATION.md",
)
LINK = re.compile(r"!?\[[^\]\n]*\]\(\s*(?:<([^>\n]+)>|([^\s)]+))(?:\s+\"[^\"\n]*\")?\s*\)")


def prose(source):
    # Ignore examples in fenced code blocks when finding links and headings.
    lines = []
    fence = None
    for line in source.splitlines():
        marker = re.match(r"^\s{0,3}(`{3,}|~{3,})", line)
        if marker:
            token = marker.group(1)
            if fence is None:
                fence = token
            elif token[0] == fence[0] and len(token) >= len(fence):
                fence = None
            continue
        if fence is None:
            lines.append(line)
    return "\n".join(lines)


def anchors(source):
    result = set()
    for title in re.findall(r"^ {0,3}#{1,6}\s+(.+?)\s*#*\s*$", prose(source), re.M):
        title = title.lower().replace("`", "").replace("*", "")
        slug = "".join(c for c in title if c in "-_ " or unicodedata.category(c)[0] in "LN").replace(" ", "-")
        anchor = slug
        suffix = 0
        while anchor in result:
            suffix += 1
            anchor = f"{slug}-{suffix}"
        result.add(anchor)
    return result


def main():
    errors = []
    # Unmodified upstream distributions may reference files omitted by their own
    # export rules. Exclude only this exact, hash-verified dependency subtree.
    from check_mail_vendor import check as check_mail_vendor
    check_mail_vendor()
    vendor = ROOT / "foundation/mail-receiving/vendor"
    for name in REQUIRED:
        path = ROOT / name
        if not path.is_file() or not path.read_text(encoding="utf-8").strip():
            errors.append(f"Missing or empty continuity document: {name}")

    documents = {path: path.read_text(encoding="utf-8") for path in ROOT.rglob("*.md") if ".git" not in path.relative_to(ROOT).parts and not path.is_relative_to(vendor)}
    local_links = 0
    heading_links = 0
    for path, source in documents.items():
        for match in LINK.finditer(prose(source)):
            destination = match.group(1) or match.group(2)
            url = urlsplit(destination)
            if url.scheme or url.netloc:
                continue
            local_links += 1
            relative = unquote(url.path)
            target = ((ROOT / relative.lstrip("/")) if relative.startswith("/") else (path.parent / relative if relative else path)).resolve()
            label = f"{path.relative_to(ROOT).as_posix()}: {destination}"
            if not target.is_relative_to(ROOT):
                errors.append(f"Link escapes repository: {label}")
            elif not target.exists():
                errors.append(f"Missing link target: {label}")
            elif url.fragment and target.suffix.lower() == ".md":
                heading_links += 1
                if unquote(url.fragment) not in anchors(target.read_text(encoding="utf-8")):
                    errors.append(f"Missing heading: {label}")

    if errors:
        print("\n".join(errors), file=sys.stderr)
        return 1
    print(f"PASS: {len(REQUIRED)} required documents; {len(documents)} Markdown files; {local_links} local links, including {heading_links} heading links.")
    print("Scope: documentation only; external URLs and application behavior are not tested.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
