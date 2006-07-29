# $Id$
#
#!/bin/sh
VERSION=0.8
cd /opt1/www/noc.ipfw.org/
tar --create --gzip --verbose --exclude='dbconnect.php' --exclude='CVS' --file=/tmp/xferlogDB.$VERSION.tar.gz xferlogDB
