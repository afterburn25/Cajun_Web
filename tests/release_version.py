"""Current test target is the source version; historical fixtures remain explicit."""
from pathlib import Path
import re
VERSION=re.search(r"const CWP_VERSION = '([^']+)'",(Path(__file__).resolve().parents[1]/'site/config/defaults.php').read_text(encoding='utf-8')).group(1)
STAGE='cwp-update-'+VERSION
RECOVERY='structure-'+VERSION
LOCK_RECOVERY='structure-1.11.0'  # Original retained inode shared by every supported updater.
