#!/bin/bash

#
# This script installs a standard shimmie setup suitable for running unit tests
# It assumes some things (though you can explicitly instruct otherwise):
# - The hostname of the shimmie test rig is the same as the hostname of the
#   current box (ie, make sure the web server has a name-based virtual host
#   listening for this name)
# - Shimmie is installed in the web root, in a folder named <version><db_type>,
#   eg /2.3s/ = shimmie 2.3.X with SQLite database (many versions and configs
#   can be installed side by side this way)
# - For configs which require a database server, the host is localhost, the
#   username and password are "shimmie"
#

HOST=`hostname`                                # domain name of the installations
CWD=`pwd`
DIR=`basename $CWD`                            # install path relative to webroot
DB_TYPE=`echo $DIR | sed "s/.*(.)/1/g"`     # last letter, eg "s", "m", or "p"
DB_NAME=s`echo $DIR | sed "s/[^a-z0-9]//g"`  # install path should be uniqueish
DB_HOST="localhost"
DB_USER="shimmie"
DB_PASS="shimmie"


RED="e[01;31m";
GREEN="e[01;32m"
CLEAR="e[0m"

function ok() { printf " ${GREEN}done${CLEAR}n" ; }
function not() { printf " ${RED}failed${CLEAR}n" ; exit ; }


function clean() {
	echo -n "Cleaning old install..." && 
	rm -rf thumbs images config.php 2>/dev/null
	if [ "$DB_TYPE" == "s" ] ; then
		rm -f $DB_NAME.sdb
	elif [ "$DB_TYPE" == "m" ] ; then
		(echo "set foreign_key_checks=off;" && mysqldump -u$DB_USER -p$DB_PASS --add-drop-table --no-data $DB_NAME | grep ^DROP) | mysql -u$DB_USER -p$DB_PASS $DB_NAME
	elif [ "$DB_TYPE" == "p" ] ; then
		export PGPASSWORD=$DB_PASS
		pg_dump -U$DB_USER $DB_NAME | grep ^DROP | psql -U$DB_USER $DB_NAME
	else
		printf " ${RED}invalid database type${CLEAR}n"
		exit
	fi
}

function create_conf() {
	echo -n "Creating auto_install.conf..."
	if [ "$DB_TYPE" == "s" ] ; then
		echo "sqlite://$DB_NAME.sdb" > auto_install.conf
	elif [ "$DB_TYPE" == "m" ] ; then
		echo "mysql://$DB_USER:$DB_PASS@$DB_HOST/$DB_NAME?persist" > auto_install.conf
	elif [ "$DB_TYPE" == "p" ] ; then
		echo "pgsql://$DB_USER:$DB_PASS@$DB_HOST/$DB_NAME?persist" > auto_install.conf
	else
		printf " ${RED}invalid database type${CLEAR}n"
		exit
	fi
}

function install_base() {
	echo -n "Installing..." && 
	curl --silent http://$HOST/$DIR/install.php > /dev/null
}

function create_user() {
	curl --silent -d name=$1 -d pass1=$1 -d pass2=$1 -d email= 
			http://$HOST/$DIR/user_admin/create > /dev/null
}

function create_users() {
	echo -n "Creating users..." && 
	create_user "demo" && 
	create_user "test"
}

clean        && ok || not
create_conf  && ok || not
install_base && ok || not
create_users && ok || not
