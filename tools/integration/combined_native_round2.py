#!/usr/bin/env python3
"""Run the retained source recipe and exact renderer-contract correction explicitly."""
from pathlib import Path
import runpy,subprocess,sys
here=Path(__file__).resolve().parent
runpy.run_path(str(here/'combined_native_round2_source.py'),run_name='__main__')
subprocess.run([sys.executable,str(here/'combined_render_contracts.py')],check=True)
