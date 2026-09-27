import re
import os

files_to_recover = ['overview.html', 'ai-insights.html', 'reports.html', 'data.html', 'ai-status.html', 'settings.html']

for f_name in files_to_recover:
    frag_path = f"recovery/{f_name}_frags.txt"
    if not os.path.exists(frag_path):
        continue
        
    with open(frag_path, 'r', encoding='utf-8') as f:
        content = f.read()
        
    # The view_file tool output has lines like: "1: <!DOCTYPE html>"
    # We want to find the largest contiguous block of numbered lines.
    
    # Or, there's another format: maybe the file content was dumped using "cat" or "Get-Content"
    # Let's try to find <!DOCTYPE html> to </html>
    
    match = re.search(r'<!DOCTYPE html>.*?</html>', content, re.IGNORECASE | re.DOTALL)
    if match:
        html = match.group(0)
        # Clean up any line numbers like "1: <!DOCTYPE html>" -> "<!DOCTYPE html>"
        # But wait, if it's from view_file, every line is prefixed with "number: "
        # Let's clean it up
        clean_lines = []
        for line in html.split('\n'):
            # if line matches "number: ", strip it
            m = re.match(r'^\d+:\s(.*)$', line)
            if m:
                clean_lines.append(m.group(1))
            else:
                clean_lines.append(line)
                
        with open(f_name, 'w', encoding='utf-8') as out_f:
            out_f.write('\n'.join(clean_lines))
        print(f"Recovered {f_name} using regex")
    else:
        print(f"Could not find HTML tags in {f_name}")
