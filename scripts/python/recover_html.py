import json
import re
import os

transcript_path = r"C:\Users\ASUS\.gemini\antigravity-ide\brain\3420ecbc-080b-447e-b74d-105d6a62a5d6\.system_generated\logs\transcript_full.jsonl"
files_to_recover = ['overview.html', 'ai-insights.html', 'reports.html', 'data.html', 'ai-status.html', 'settings.html']
recovered = {f: False for f in files_to_recover}

with open(transcript_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

for line in reversed(lines):
    if all(recovered.values()):
        break
    try:
        data = json.loads(line)
        if data.get('type') == 'TOOL_RESPONSE' and data.get('tool_name') == 'view_file':
            # Check if this tool response contains the content of a file we need
            content = data.get('content', '')
            for f_name in files_to_recover:
                if not recovered[f_name] and f_name in content:
                    # Look for "File Path: ...\f_name" and "Total Lines:"
                    if f"File Path: `file:///c:/laragon/www/spacial/{f_name}`" in content.lower():
                        # The content format is:
                        # File Path: ...
                        # Total Lines: ...
                        # Total Bytes: ...
                        # Showing lines 1 to ...
                        # The following code has been modified...
                        # <line_number>: <original_line>
                        
                        # Extract the lines
                        lines_content = []
                        for c_line in content.split('\n'):
                            match = re.match(r'^\d+:\s(.*)$', c_line)
                            if match:
                                lines_content.append(match.group(1))
                        
                        if lines_content:
                            with open(f_name, 'w', encoding='utf-8') as out_f:
                                out_f.write('\n'.join(lines_content))
                            recovered[f_name] = True
                            print(f"Recovered {f_name} from view_file")
    except Exception as e:
        pass

for f_name, rec in recovered.items():
    if not rec:
        print(f"Could not recover {f_name}")
