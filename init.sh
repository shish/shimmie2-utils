#!/bin/bash

echo "Checking out source code..."
git clone git@github.com:shish/shimmie2.git 2.Xm
cp -r 2.Xm 2.Xp
cp -r 2.Xm 2.Xs

cp -r 2.Xm 2.3m
cd 2.3m
git checkout -b branch_2.3 origin/branch_2.3
git branch -D master
cd ..
cp -r 2.3m 2.3p
cp -r 2.3m 2.3s

echo "Installing reset scripts"
for n in 2.?? ; do
	cd $n
	ln -s ../shimmie2-utils/reset.sh ./
	cd ..
done

echo "Installing simpletest"
for n in 2.?? ; do
	cd $n/ext
	ln -s ../contrib/simpletest ./
	cd ../..
done

echo "Creating database users"
sudo -u postgres psql -c "create user shimmie with password 'shimmie' createdb;"
sudo mysql -uroot -pshimmie -e "create user 'shimmie'@'localhost' identified by 'shimmie'";
sudo mysql -uroot -pshimmie -e "grant all on *.* to 'shimmie'@'localhost'";

echo "Configuring web server"
sudo rm -f /etc/nginx/sites-enabled/default
sed "s/@INSTALLDIR@/`pwd`/" shimmie2-utils/shimtest.nginx.conf | sudo tee /etc/nginx/sites-available/shimtest > /dev/null
sudo ln -sf /etc/nginx/sites-available/shimtest /etc/nginx/sites-enabled/shimtest
sudo /etc/init.d/nginx reload
