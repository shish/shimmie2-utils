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
