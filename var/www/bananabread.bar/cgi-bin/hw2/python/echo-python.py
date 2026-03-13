#!/usr/bin/env python3
import os, json, datetime, urllib.parse, socket, sys

method = os.environ.get("REQUEST_METHOD", "UNKNOWN")
ct = os.environ.get("CONTENT_TYPE", "")
ua = os.environ.get("HTTP_USER_AGENT", "")
ip = os.environ.get("REMOTE_ADDR", "unknown")
host = socket.gethostname()

length = int(os.environ.get("CONTENT_LENGTH", "0") or "0")
raw = sys.stdin.read(length) if length > 0 else ""

received = None
if "application/json" in ct.lower():
  try:
    received = json.loads(raw) if raw else {}
  except:
    received = {"_error": "invalid json", "_raw": raw}
else:
  qs = os.environ.get("QUERY_STRING", "")
  combined = "&".join([p for p in [qs, raw] if p])
  received = urllib.parse.parse_qs(combined, keep_blank_values=True)

out = {
  "language": "Python",
  "endpoint": "echo-python",
  "method": method,
  "content_type": ct,
  "hostname": host,
  "time": datetime.datetime.now().astimezone().isoformat(),
  "ip": ip,
  "user_agent": ua,
  "received": received,
  "raw_body": raw,
}

print("Content-Type: application/json; charset=utf-8")
print()
print(json.dumps(out, indent=2))
