#!/usr/bin/env ruby
puts "Content-Type: text/plain; charset=utf-8"
puts
ENV.sort.each { |k,v| puts "#{k}=#{v}" }
