#!/usr/bin/env python3
"""Compile vs-download .po files to .mo (GNU gettext format)."""
from __future__ import annotations

import struct
import sys
from pathlib import Path


def parse_po(path: Path) -> dict[str, str]:
    entries: dict[str, str] = {}
    msgid: str | None = None
    msgstr_parts: list[str] = []
    in_msgstr = False

    def flush() -> None:
        nonlocal msgid, msgstr_parts, in_msgstr
        if msgid is not None and msgid != "":
            entries[msgid] = "".join(msgstr_parts)
        msgid = None
        msgstr_parts = []
        in_msgstr = False

    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if line.startswith("msgid "):
            flush()
            msgid = unquote(line[6:])
            in_msgstr = False
        elif line.startswith("msgstr "):
            msgstr_parts = [unquote(line[7:])]
            in_msgstr = True
        elif line.startswith('"'):
            chunk = unquote(line)
            if in_msgstr:
                msgstr_parts.append(chunk)
            elif msgid is not None:
                msgid += chunk
        elif line == "":
            flush()

    flush()
    return entries


def unquote(s: str) -> str:
    s = s.strip()
    if not s:
        return ""
    if s.startswith('"') and s.endswith('"'):
        s = s[1:-1]
    return s.replace("\\n", "\n").replace('\\"', '"').replace("\\\\", "\\")


def write_mo(path: Path, entries: dict[str, str]) -> None:
    pairs: list[tuple[str, str]] = [("", "")]
    for key in sorted(entries.keys()):
        pairs.append((key, entries[key]))

    originals = b""
    translations = b""
    orig_index: list[tuple[int, int]] = []
    trans_index: list[tuple[int, int]] = []

    origin = 28 + 16 * len(pairs)
    o_off = origin
    for key, _val in pairs:
        data = key.encode("utf-8") + b"\x00"
        orig_index.append((len(data) - 1, o_off))
        originals += data
        o_off += len(data)

    t_off = origin + len(originals)
    for _key, val in pairs:
        data = val.encode("utf-8") + b"\x00"
        trans_index.append((len(data) - 1, t_off))
        translations += data
        t_off += len(data)

    count = len(pairs)
    o_table = 28
    t_table = 28 + 8 * count

    out = bytearray()
    out += struct.pack("<I", 0x950412DE)
    out += struct.pack("<I", 0)
    out += struct.pack("<I", count)
    out += struct.pack("<I", o_table)
    out += struct.pack("<I", t_table)
    out += struct.pack("<I", 0)
    out += struct.pack("<I", 0)

    for length, offset in orig_index:
        out += struct.pack("<II", length, offset)
    for length, offset in trans_index:
        out += struct.pack("<II", length, offset)

    out += originals + translations
    path.write_bytes(out)


def main() -> int:
    lang_dir = Path(__file__).resolve().parent.parent / "languages"
    pos = sorted(lang_dir.glob("vs-download-*.po"))
    if not pos:
        print("No .po files found.", file=sys.stderr)
        return 1

    for po in pos:
        mo = po.with_suffix(".mo")
        entries = parse_po(po)
        write_mo(mo, entries)
        print(f"Compiled {mo.name} ({len(entries)} strings)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
