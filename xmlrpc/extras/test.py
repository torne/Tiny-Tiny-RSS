#!/usr/bin/python

from xmlrpclib import *
import sys

server = Server("http://madoka.spb.ru/~fox/testbox/tt-rss/xml-rpc.php")

try:
#	print server.rss.getAllFeeds("fox", "sotona");

	print server.rss.subscribeToFeed("admin", "password", 
		"http://tt-rss.spb.ru/forum/rss.php")

	r = server.rss.getSubscribedFeeds("admin", "password")
	print r
	
#    print "Got '" + server.examples.getStateName(32) + "'"
#
#    r = server.mail.send("edd", "Test",
#                         "Bonjour.", "freddy", "", "", 
#                         'text/plain; charset="iso-8859-1"')
#    if r:
#        print "Mail sent OK"
#    else:
#        print "Error sending mail"
#
#
#    r = server.examples.echo('Three "blind" mice - ' + "See 'how' they run")
#    print r
#
#    # name/age example. this exercises structs and arrays
#
#    a = [ {'name': 'Dave', 'age': 35}, {'name': 'Edd', 'age': 45 },
#          {'name': 'Fred', 'age': 23}, {'name': 'Barney', 'age': 36 }]
#    r = server.examples.sortByAge(a)
#    print r
#
#    # test base 64
#    b = Binary("Mary had a little lamb She tied it to a pylon")
#    b.encode(sys.stdout)
#    r = server.examples.decode64(b)
#    print r
    
except Error, v:
    print "XML-RPC Error:",v
