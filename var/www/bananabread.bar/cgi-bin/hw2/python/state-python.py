#!/usr/bin/env python3
import os, urllib.parse
from http import cookies

def get_cookie(name: str, default="(none)"):
    raw = os.environ.get("HTTP_COOKIE", "")
    c = cookies.SimpleCookie()
    c.load(raw)
    return c.get(name).value if name in c else default

uid = get_cookie("uid")

def parse_cookie(header: str):
  out = {}
  parts = header.split(";")
  for p in parts:
    if "=" in p:
      k, v = p.strip().split("=", 1)
      out[k] = v
  return out

method = os.environ.get("REQUEST_METHOD", "GET")
qs = urllib.parse.parse_qs(os.environ.get("QUERY_STRING", ""))
action = (qs.get("action", ["view"])[0])

cookie_header = os.environ.get("HTTP_COOKIE", "")
cookies = parse_cookie(cookie_header)
current = urllib.parse.unquote(cookies.get("hw2_state_python", "")) if cookies.get("hw2_state_python") else "(nothing saved)"
msg = ""

set_cookie_line = None

if action == "set" and method == "POST":
  length = int(os.environ.get("CONTENT_LENGTH", "0") or "0")
  body = os.sys.stdin.read(length) if length > 0 else ""
  form = urllib.parse.parse_qs(body)
  value = form.get("value", [""])[0]
  msg = "Saved!"
  current = value
  set_cookie_line = "Set-Cookie: hw2_state_python=" + urllib.parse.quote(value) + "; Path=/; Max-Age=3600"
elif action == "clear":
  msg = "Cleared!"
  current = "(nothing saved)"
  set_cookie_line = "Set-Cookie: hw2_state_python=; Path=/; Max-Age=0"

print("Content-Type: text/html; charset=utf-8")
if set_cookie_line:
  print(set_cookie_line)
print()
print(f"""<!doctype html>
<html><head><script src="https://cdn.logr-in.com/LogRocket.min.js" crossorigin="anonymous"></script>
    <script>window.LogRocket && window.LogRocket.init('c2uo5x/bananabreadbar');</script>
    <meta charset="utf-8"><title>State (Python)</title></head>
<body>
  <h1>State Demo (Python)</h1>
  {"<p><b>"+msg+"</b></p>" if msg else ""}
  <p>Current saved value: <b>{current}</b></p>

  <h2>Save</h2>
  <form method="POST" action="/cgi-bin/hw2/python/state-python.py?action=set">
    <input name="value" placeholder="type something">
    <button type="submit">Save</button>
  </form>

  <h2>Clear</h2>
  <form method="GET" action="/cgi-bin/hw2/python/state-python.py">
    <input type="hidden" name="action" value="clear">
    <button type="submit">Clear</button>
  </form>
  <p><a href="/cgi-bin/hw2/python/state-python.py">Refresh/View</a></p>
  <h2>Fingerprint</h3>
  <p>Fingerprint UID: <b>{uid}</b></p>
  <p><a href="/hw2/fingerprint.html">Run fingerprint reassociation</a></p>
</body></html>""")
