#!/usr/bin/env ruby
require 'time'

ip = ENV['REMOTE_ADDR'] || 'unknown'
now = Time.now.iso8601
team = "Naina, Dante, Hisham"

puts "Content-Type: text/html; charset=utf-8"
puts
puts <<HTML
<!doctype html>
<html><head>
 <script src="https://cdn.logr-in.com/LogRocket.min.js" crossorigin="anonymous"></script>
 <script>window.LogRocket && window.LogRocket.init('c2uo5x/bananabreadbar');</script><meta charset="utf-8"><title>Hello HTML (Ruby)</title></head>
<body>
  <h1>Hello from #{team}!</h1>
  <p>Language: <b>Ruby</b></p>
  <p>Generated at: <b>#{now}</b></p>
  <p>Your IP address is: <b>#{ip}</b></p>
</body></html>
HTML
