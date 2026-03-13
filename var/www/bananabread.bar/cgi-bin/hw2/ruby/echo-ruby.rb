#!/usr/bin/env ruby
require 'json'
require 'time'
require 'uri'
require 'socket'

method = ENV["REQUEST_METHOD"] || "UNKNOWN"
ct = ENV["CONTENT_TYPE"] || ""
ua = ENV["HTTP_USER_AGENT"] || ""
ip = ENV["REMOTE_ADDR"] || "unknown"
host = Socket.gethostname

len = (ENV["CONTENT_LENGTH"] || "0").to_i
raw = len > 0 ? STDIN.read(len) : ""

received = nil
if ct.downcase.include?("application/json")
  begin
    received = raw.empty? ? {} : JSON.parse(raw)
  rescue
    received = {"_error" => "invalid json", "_raw" => raw}
  end
else
  qs = ENV["QUERY_STRING"] || ""
  combined = [qs, raw].reject(&:empty?).join("&")
  received = URI.decode_www_form(combined).group_by(&:first).transform_values { |arr| arr.map(&:last) }
end

out = {
  language: "Ruby",
  endpoint: "echo-ruby",
  method: method,
  content_type: ct,
  hostname: host,
  time: Time.now.iso8601,
  ip: ip,
  user_agent: ua,
  received: received,
  raw_body: raw
}

puts "Content-Type: application/json; charset=utf-8"
puts
puts JSON.pretty_generate(out)
