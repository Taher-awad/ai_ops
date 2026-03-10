import json
import os

LOG_FILE = "storage/logs/aiops.log"
OUT_FILE = "logs.json"

if not os.path.exists(LOG_FILE):
    print(f"Log file {LOG_FILE} not found!")
    exit(1)

exported = []
with open(LOG_FILE, "r") as f:
    for line in f:
        line = line.strip()
        if not line:
            continue
        try:
            data = json.loads(line)
            # The context contains our structured log schema
            if "context" in data and "request_id" in data["context"]:
                # Add datetime to context for completeness
                context = data["context"]
                context["timestamp"] = data.get("datetime", "")
                exported.append(context)
        except json.JSONDecodeError:
            pass

with open(OUT_FILE, "w") as f:
    json.dump(exported, f, indent=4)

print(f"Exported {len(exported)} logs to {OUT_FILE}")
