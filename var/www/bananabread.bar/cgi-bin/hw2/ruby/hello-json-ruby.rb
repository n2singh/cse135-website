#!/usr/bin/env ruby
require 'json'
require 'time'

out = {
  message: "Hello from the team!",
  team: ["Naina", "Dante", "Hisham"],
  language: "Ruby",
  generated_at: Time.now.iso8601,
  ip: ENV["REMOTE_ADDR"] || "unknown"
}

puts "Content-Type: application/json; charset=utf-8"
puts
puts JSON.pretty_generate(out)
