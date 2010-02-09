#!/bin/bash

# postgresql mysql-server apache2-mpm-itk apache2-mod-php5 php5-gd php5-mysql php5-pgsql curl wget git-core
# screen vim sudo

echo "Checking out source code..."
git clone git://git.shishnet.org/shimmie2.git 2.Xm
cp -r 2.Xm 2.Xp
cp -r 2.Xm 2.Xs

cp -r 2.Xm 2.3m
cd 2.3m
git checkout -b branch_2.3 origin/branch_2.3
git branch -D master
cd ..
cp -r 2.3m 2.3p
cp -r 2.3m 2.3s

echo "Creating scripts"
cat > sync.sh <<EOD
#!/bin/sh
for n in 2.?? ; do
	echo
	echo \$n
	cd \$n
	git pull || true
	#git push || true
	cd ..
done
EOD
chmod +x sync.sh

mkdir dev_misc
cat > dev_misc/reset.sh <<EOD
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

HOST=\`hostname\`                                # domain name of the installations
CWD=\`pwd\`
DIR=\`basename \$CWD\`                            # install path relative to webroot
DB_TYPE=\`echo \$DIR | sed "s/.*\(.\)/\1/g"\`     # last letter, eg "s", "m", or "p"
DB_NAME=s\`echo \$DIR | sed "s/[^a-z0-9]//g"\`  # install path should be uniqueish
DB_HOST="localhost"
DB_USER="shimmie"
DB_PASS="shimmie"


RED="\\e[01;31m";
GREEN="\\e[01;32m"
CLEAR="\\e[0m"

function ok() { printf " \${GREEN}done\${CLEAR}\\n" ; }
function not() { printf " \${RED}failed\${CLEAR}\\n" ; exit ; }


function clean() {
	echo -n "Cleaning old install..." && \
	rm -rf thumbs images config.php 2>/dev/null
	if [ "\$DB_TYPE" == "s" ] ; then
		rm -f \$DB_NAME.sdb
	elif [ "\$DB_TYPE" == "m" ] ; then
		mysqldump -u\$DB_USER -p\$DB_PASS --add-drop-table --no-data \$DB_NAME | grep ^DROP | mysql -u\$DB_USER -p\$DB_PASS \$DB_NAME
	elif [ "\$DB_TYPE" == "p" ] ; then
		export PGPASSWORD=\$DB_PASS
		pg_dump -U\$DB_USER \$DB_NAME | grep ^DROP | psql -U\$DB_USER \$DB_NAME
	else
		printf " \${RED}invalid database type\${CLEAR}\\n"
		exit
	fi
}

function create_conf() {
	echo -n "Creating auto_install.conf..."
	if [ "\$DB_TYPE" == "s" ] ; then
		echo "sqlite://\$DB_NAME.sdb" > auto_install.conf
	elif [ "\$DB_TYPE" == "m" ] ; then
		echo "mysql://\$DB_USER:\$DB_PASS@\$DB_HOST/\$DB_NAME?persist" > auto_install.conf
	elif [ "\$DB_TYPE" == "p" ] ; then
		echo "pgsql://\$DB_USER:\$DB_PASS@\$DB_HOST/\$DB_NAME?persist" > auto_install.conf
	else
		printf " \${RED}invalid database type\${CLEAR}\\n"
		exit
	fi
}

function install_base() {
	echo -n "Installing..." && \
	curl --silent http://\$HOST/\$DIR/install.php > /dev/null
}

function create_user() {
	curl --silent -d name=\$1 -d pass1=\$1 -d pass2=\$1 -d email= \
			http://\$HOST/\$DIR/user_admin/create > /dev/null
}

function create_users() {
	echo -n "Creating users..." && \
	create_user "demo" && \
	create_user "test"
}

clean        && ok || not
create_conf  && ok || not
install_base && ok || not
create_users && ok || not
EOD
chmod +x dev_misc/reset.sh

echo "Installing reset scripts"
for n in 2.?? ; do
	cd $n
	ln -s ../dev_misc/reset.sh ./
	cd ..
done

echo "Installing simpletest"
for n in 2.?? ; do
	cd $n/ext
	ln -s ../contrib/simpletest ./
	cd ../..
done

echo "Archiving self"
mv $0 ./dev_misc/init_dev.old
chmod -x ./dev_misc/init_dev.old
