import json

transcript_path = r"C:\Users\ASUS\.gemini\antigravity-ide\brain\3420ecbc-080b-447e-b74d-105d6a62a5d6\.system_generated\logs\transcript_full.jsonl"
files_to_recover = ['reports.html', 'data.html', 'ai-status.html']
recovered = {f: False for f in files_to_recover}

with open(transcript_path, 'r', encoding='utf-8') as f:
    for line in reversed(f.readlines()):
        if all(recovered.values()):
            break
        try:
            data = json.loads(line)
            # Check for tool_calls (this is where the input to write_to_file is)
            if 'tool_calls' in data:
                for tc in data['tool_calls']:
                    if tc.get('function', {}).get('name') == 'default_api:write_to_file':
                        args = tc.get('function', {}).get('arguments', '')
                        if args:
                            args_json = json.loads(args)
                            target = args_json.get('TargetFile', '')
                            for f_name in files_to_recover:
                                if not recovered[f_name] and target.endswith(f_name):
                                    code = args_json.get('CodeContent', '')
                                    if code:
                                        with open(f_name, 'w', encoding='utf-8') as out_f:
                                            out_f.write(code)
                                        recovered[f_name] = True
                                        print(f"Recovered {f_name} from write_to_file arguments")
        except:
            pass

for f_name, rec in recovered.items():
    if not rec:
        print(f"Could not recover {f_name}")
