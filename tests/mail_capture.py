"""Local PHP mail() sink; writes synthetic messages only, never opens a network."""
from pathlib import Path
import sys
message=sys.stdin.buffer.read(100000)
if b'@example.invalid' not in message:
    raise SystemExit('Only fixture mail may be captured')
target=Path(__file__).resolve().parents[1]/'qa/mail-capture.txt'
with target.open('ab') as out: out.write(message+b'\n---END---\n')
