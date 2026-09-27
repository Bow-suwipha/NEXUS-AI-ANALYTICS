import json
import re

transcript_path = r"C:\Users\ASUS\.gemini\antigravity-ide\brain\3420ecbc-080b-447e-b74d-105d6a62a5d6\.system_generated\logs\transcript_full.jsonl"

html_blocks = []

with open(transcript_path, 'r', encoding='utf-8') as f:
    content = f.read()
    
# Find all occurrences of <!DOCTYPE html> ... </html>
matches = re.finditer(r'<!DOCTYPE html>.*?</html>', content, re.IGNORECASE | re.DOTALL)
for i, m in enumerate(matches):
    html = m.group(0)
    
    # clean it up if it has line numbers
    clean_lines = []
    for line in html.split('\n'):
        # match line numbers
        match = re.match(r'^\d+:\s(.*)$', line)
        if match:
            clean_lines.append(match.group(1))
        else:
            # Maybe the text is literally inside JSON strings, so it has \n instead of actual newlines.
            # But f.read() reads the raw file, so \n is literally backslash n!
            pass

# Actually, if it's JSON, the HTML is inside a JSON string field!
# So we should parse JSON line by line!

html_blocks_json = []
with open(transcript_path, 'r', encoding='utf-8') as f:
    for line in f:
        try:
            data = json.loads(line)
            # convert data to string and search for HTML
            data_str = json.dumps(data, ensure_ascii=False)
            matches_in_json = re.finditer(r'<!DOCTYPE html>(?:\\n|.)*?</html>', data_str, re.IGNORECASE)
            for m in matches_in_json:
                raw_html = m.group(0)
                # Decode JSON string escapes
                try:
                    decoded = json.loads('"' + raw_html.replace('"', '\\"') + '"')
                    html_blocks_json.append(decoded)
                except:
                    # manual decode
                    decoded = raw_html.replace('\\n', '\n').replace('\\t', '\t').replace('\\"', '"')
                    html_blocks_json.append(decoded)
        except Exception as e:
            pass

for i, b in enumerate(html_blocks_json):
    with open(f"extracted_{i}.html", 'w', encoding='utf-8') as out_f:
        out_f.write(b)

print(f"Extracted {len(html_blocks_json)} HTML blocks.")
