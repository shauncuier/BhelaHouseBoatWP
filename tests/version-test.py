#!/usr/bin/env python3
"""Assert every BHELA version field agrees.

The theme and the plugin share one version number, written in several places. Two
of them — BHELA_VERSION and BHELA_BM_VERSION — are asset cache-busters, so when
they lag behind style.css the failure is invisible: the header reads correctly,
WordPress reports the new version, and browsers keep serving the old CSS and JS
from cache. That has shipped before. This is the check that catches it.

Two callers, one implementation:

  * tests/run.php runs this alongside the PHP harnesses (pre-release gate).
  * A PostToolUse hook runs it with --hook after any edit to one of the five
    files, so drift surfaces while you are still editing rather than at release.

Deliberately free of dependencies and of WordPress: it is plain file parsing,
and the hook path has to work in a shell where neither php nor jq exists.
"""

import json
import re
import sys
from pathlib import Path

WP_CONTENT = Path(__file__).resolve().parent.parent

# (path, regex capturing the version, human label)
FIELDS = [
    ("themes/bhela/style.css",
     r"^\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$",
     "theme style.css header"),
    ("themes/bhela/README.md",
     r"^#.*\(v([0-9]+\.[0-9]+\.[0-9]+)\)",
     "theme README title"),
    ("themes/bhela/functions.php",
     r"define\(\s*'BHELA_VERSION'\s*,\s*'([0-9]+\.[0-9]+\.[0-9]+)'\s*\)",
     "BHELA_VERSION (theme cache-buster)"),
    ("plugins/bhela-booking/bhela-booking.php",
     r"^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$",
     "plugin header"),
    ("plugins/bhela-booking/bhela-booking.php",
     r"define\(\s*'BHELA_BM_VERSION'\s*,\s*'([0-9]+\.[0-9]+\.[0-9]+)'\s*\)",
     "BHELA_BM_VERSION (plugin cache-buster)"),
    # A sixth, added after the fact: CLAUDE.md §7 lists five fields, and this
    # one — outside that list and so watched by nobody — had quietly sat at
    # 2.22.0 through the whole 2.23.0 release. Tracking it is the point.
    ("plugins/bhela-booking/README.md",
     r"^-\s*\*\*Version:\*\*\s*([0-9]+\.[0-9]+\.[0-9]+)",
     "plugin README"),
]

# The files a hook should react to, as repo-relative paths.
WATCHED = sorted({path for path, _, _ in FIELDS})


def read_versions():
    """Return [(label, path, version_or_None), ...] in declaration order."""
    found = []
    for rel, pattern, label in FIELDS:
        f = WP_CONTENT / rel
        if not f.is_file():
            found.append((label, rel, None))
            continue
        text = f.read_text(encoding="utf-8", errors="replace")
        m = re.search(pattern, text, re.M)
        found.append((label, rel, m.group(1) if m else None))
    return found


def touched_a_version_file():
    """True when the hook payload on stdin edited one of the five files.

    Doing the filtering here rather than in the shell keeps the hook command to
    a single portable line — there is no jq on this machine, and the matcher
    can only narrow to a tool name, not a path.
    """
    try:
        payload = json.load(sys.stdin)
    except Exception:
        return False
    inp = payload.get("tool_input") or {}
    path = inp.get("file_path") or (payload.get("tool_response") or {}).get("filePath") or ""
    path = str(path).replace("\\", "/")
    return any(path.endswith(w) for w in WATCHED)


def main():
    if "--hook-stdin" in sys.argv:
        if not touched_a_version_file():
            return 0
        sys.argv.append("--hook")

    hook_mode = "--hook" in sys.argv
    found = read_versions()
    versions = [v for _, _, v in found]
    distinct = sorted({v for v in versions if v})
    missing = [(label, rel) for label, rel, v in found if not v]
    drifted = len(distinct) > 1 or bool(missing)

    if hook_mode:
        # Warn, never block. A hook that stops an edit mid-bump would fire on
        # every release: the fields are necessarily out of step between the
        # first edit and the fifth.
        if drifted:
            majority = max(distinct, key=versions.count) if distinct else "?"
            lines = [
                "Version fields are out of sync — all %d must match "
                "before release (CLAUDE.md §7)." % len(FIELDS),
            ]
            for label, rel, v in found:
                mark = " " if v == majority else "←"
                lines.append("  %s %-34s %s  (%s)" % (mark, label, v or "NOT FOUND", rel))
            print(json.dumps({"systemMessage": "\n".join(lines)}))
        return 0

    print("  All %d version fields must agree "
          "(theme + plugin share one number).\n" % len(FIELDS))
    for label, rel, v in found:
        ok = bool(v) and len(distinct) == 1
        print("  [%s] %-34s %s" % ("PASS" if ok else "FAIL", label, v or "NOT FOUND"))
    if drifted:
        print("\n*** VERSIONS OUT OF SYNC: %s ***" % (", ".join(distinct) or "none found"))
        for label, rel in missing:
            print("    could not read %s in %s" % (label, rel))
        print("\n    They all move together. See CLAUDE.md §7.")
        return 1
    print("\nALL CHECKS PASSED (%d fields at %s)" % ( len( FIELDS ), distinct[0] ))
    return 0


if __name__ == "__main__":
    sys.exit(main())
