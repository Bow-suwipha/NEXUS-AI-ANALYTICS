import os
import re

replacements = {
    "สาขา A": "สาขากรุงเทพฯ",
    "สาขา B": "สาขาเชียงใหม่",
    "สาขา C": "สาขาขอนแก่น",
    "สาขา D": "สาขาชลบุรี"
}

files_to_update = [
    "c:\\laragon\\www\\spacial\\sales-analysis.html",
    "c:\\laragon\\www\\spacial\\overview.html",
    "c:\\laragon\\www\\spacial\\ai-insights.html",
    "c:\\laragon\\www\\spacial\\backend\\main.py"
]

for file_path in files_to_update:
    if os.path.exists(file_path):
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
            
        original_content = content
        for old_str, new_str in replacements.items():
            content = content.replace(old_str, new_str)
            
        if content != original_content:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Updated {file_path}")
    else:
        print(f"File not found: {file_path}")
