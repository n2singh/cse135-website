#!/usr/bin/env ruby
require 'uri'
require 'time'
require 'cgi'
cgi = CGI.new

uid = cgi.cookies['uid']&.first || '(none)'

def parse_cookie(header)
  cookies = {}
  header.to_s.split(";").each do |p|
    k,v = p.strip.split("=", 2)
    cookies[k] = v if k && v
  end
  cookies
end

method = ENV["REQUEST_METHOD"] || "GET"
qs = URI.decode_www_form(ENV["QUERY_STRING"].to_s).to_h
action = qs["action"] || "view"

cookies = parse_cookie(ENV["HTTP_COOKIE"])
current = cookies["hw2_state_ruby"] ? URI.decode_www_form_component(cookies["hw2_state_ruby"]) : "(nothing saved)"
msg = ""
set_cookie = nil

if action == "set" && method == "POST"
  len = (ENV["CONTENT_LENGTH"] || "0").to_i
  body = len > 0 ? STDIN.read(len) : ""
  form = URI.decode_www_form(body).to_h
  value = form["value"].to_s
  msg = "Saved!"
  current = value
  set_cookie = "hw2_state_ruby=#{URI.encode_www_form_component(value)}; Path=/; Max-Age=3600"
elsif action == "clear"
  msg = "Cleared!"
  current = "(nothing saved)"
  set_cookie = "hw2_state_ruby=; Path=/; Max-Age=0"
end

puts "Content-Type: text/html; charset=utf-8"
puts "Set-Cookie: #{set_cookie}" if set_cookie
puts
puts <<HTML
<!doctype html>
<html>
<head>
    <script src="https://cdn.logr-in.com/LogRocket.min.js" crossorigin="anonymous"></script>
    <script>window.LogRocket && window.LogRocket.init('c2uo5x/bananabreadbar');</script>
    <meta charset="utf-8"><title>State (Ruby)</title>
</head>
<body>
  <h1>State Demo (Ruby)</h1>
  #{msg.empty? ? "" : "<p><b>#{msg}</b></p>"}
  <p>Current saved value: <b>#{current}</b></p>

  <h2>Save</h2>
  <form method="POST" action="/cgi-bin/hw2/ruby/state-ruby.rb?action=set">
    <input name="value" placeholder="type something">
    <button type="submit">Save</button>
  </form>

  <h2>Clear</h2>
  <form method="GET" action="/cgi-bin/hw2/ruby/state-ruby.rb">
    <input type="hidden" name="action" value="clear">
    <button type="submit">Clear</button>
  </form>

  <p><a href="/cgi-bin/hw2/ruby/state-ruby.rb">Refresh/View</a></p>

  <h2>Fingerprint</h2>
  <p>Fingerprint UID: <b>#{CGI.escapeHTML(uid)}</b></p>
  <p><a href="/hw2/fingerprint.html">Run fingerprint reassociation</a></p>
</body></html>
HTML
