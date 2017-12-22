#!/bin/bash

VERSION="2.1"
IDENTIFIER="com.solarwindsmsp.bluesky.admin.pkg"
APPNAME="BlueSkyAdmin"

# create folders to work in
mkdir -p /tmp/pkg
mkdir /tmp/pkg-flat 2>/dev/null
mkdir /tmp/pkg-payload 2>/dev/null

# clean up old files
rm -rf /tmp/pkg-flat/*
rm -rf /tmp/pkg-payload/*
rm -rf /tmp/pkg-payload/.* 2>/dev/null
rm -rf /tmp/pkg/BlueSkyAdmin-*.pkg

# copy the files we want to go into the pkg
cp -RL /usr/local/bin/BlueSky/Admin\ Tools/* /tmp/pkg-payload/

# fix up the admin tools for deployment
cp /tmp/pkg-payload/server.txt /tmp/pkg-payload/BlueSky\ Admin\ Setup.app/Contents/Resources/
cp /tmp/pkg-payload/server.txt /tmp/pkg-payload/BlueSky\ Admin.app/Contents/Resources/
cp /tmp/pkg-payload/server.txt /tmp/pkg-payload/BlueSky\ Temporary\ Client.app/Contents/Resources/
cp /tmp/pkg-payload/blueskyadmin.pub /tmp/pkg-payload/BlueSky\ Admin\ Setup.app/Contents/Resources/
cp /tmp/pkg-payload/blueskyadmin.pub /tmp/pkg-payload/BlueSky\ Admin.app/Contents/Resources/
cp -L /usr/local/bin/BlueSky/Client/blueskyclient.pub /tmp/pkg-payload/BlueSky\ Temporary\ Client.app/Contents/Resources/
rm /tmp/pkg-payload/server.txt /tmp/pkg-payload/blueskyadmin.pub

# get info about our payload
NUM_FILES=$(find /tmp/pkg-payload | wc -l)
INSTALL_KB_SIZE=$(du -k -s /tmp/pkg-payload | awk '{print $1}')

# write out the PackageInfo file to flat pkg location
cat <<EOF > /tmp/pkg-flat/PackageInfo
<?xml version="1.0" encoding="utf-8"?>
<pkg-info postinstall-action="none" format-version="2" identifier="${IDENTIFIER}" version="${VERSION}" generator-version="InstallCmds-611 (16G1036)" install-location="/Applications/Utilities" auth="root">
    <payload numberOfFiles="${NUM_FILES}" installKBytes="${INSTALL_KB_SIZE}"/>
    <bundle-version/>
    <upgrade-bundle/>
    <update-bundle/>
    <atomic-update-bundle/>
    <strict-identifier/>
    <relocate/>
    <scripts/>
</pkg-info>
EOF

PKG_LOCATION="/tmp/pkg/${APPNAME}-${VERSION}.pkg"

# compress the payload
( cd /tmp/pkg-payload && find . | cpio -o --format odc --owner 0:80 | gzip -c ) > /tmp/pkg-flat/Payload
# create Bom file
( cd /tmp/pkg-payload && ls4mkbom -u 0 -g 80 . ) > /tmp/pkg/.bom
mkbom -i /tmp/pkg/.bom /tmp/pkg-flat/Bom
rm -f /tmp/pkg/.bom
# pkg it up!!
( cd /tmp/pkg-flat && xar --compression none -cf "${PKG_LOCATION}" * )
echo "osx package has been built: ${PKG_LOCATION}"

RANDOM_DIR=`cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w ${1:-32} | head -n 1`
mkdir /var/www/html/"${RANDOM_DIR}"
ln -s "${PKG_LOCATION}" /var/www/html/"${RANDOM_DIR}"/
cat <<EOF >> /var/www/html/hooks/header-extras.php
		<button onClick="window.location='${RANDOM_DIR}/${APPNAME}-${VERSION}.pkg';" class="btn btn-default"><i class="glyphicon glyphicon-download-alt"></i> Download BlueSky Admin Tools</button>
	</div>
	<div class="clearfix">
	</div>
	<p>
	</p>
</div>
<?php } ?>
EOF
