#!/bin/bash

VERSION="1.0"
IDENTIFIER="com.solarwindsmsp.bluesky.pkg"
APPNAME="BlueSky"

# create folders to work in
mkdir -p /tmp/pkg
mkdir /tmp/pkg-flat
mkdir /tmp/pkg-payload
mkdir /tmp/pkg-scripts

# clean up old files
rm -rf /tmp/pkg-flat/*
rm -rf /tmp/pkg-payload/*
rm -rf /tmp/pkg-payload/.*
rm -rf /tmp/pkg-scripts/*
rm -rf /tmp/pkg/BlueSky-*.pkg

# copy the files we want to go into the pkg and get info about them
cp -RL /usr/local/bin/BlueSky/Client/* /tmp/pkg-payload/
cp -R /usr/local/bin/BlueSky/Client/.ssh /tmp/pkg-payload/
NUM_FILES=$(find /tmp/pkg-payload | wc -l)
INSTALL_KB_SIZE=$(du -k -s /tmp/pkg-payload | awk '{print $1}')

# write out the PackageInfo file to flat pkg location
cat <<EOF > /tmp/pkg-flat/PackageInfo
<?xml version="1.0" encoding="utf-8"?>
<pkg-info postinstall-action="none" format-version="2" identifier="${IDENTIFIER}" version="${VERSION}" generator-version="InstallCmds-611 (16G1036)" install-location="/var/bluesky" auth="root">
    <payload numberOfFiles="${NUM_FILES}" installKBytes="${INSTALL_KB_SIZE}"/>
    <bundle-version/>
    <upgrade-bundle/>
    <update-bundle/>
    <atomic-update-bundle/>
    <strict-identifier/>
    <relocate/>
    <scripts>
        <preinstall file="./preinstall"/>
        <postinstall file="./postinstall"/>
    </scripts>
</pkg-info>
EOF

# write out the Scripts
cat <<EOF > /tmp/pkg-scripts/preinstall
#!/bin/bash
mkdir -p /var/bluesky
exit 0
EOF

cat <<EOF > /tmp/pkg-scripts/postinstall
#!/bin/bash
/var/bluesky/helper.sh
exit 0
EOF

# make sure they are executed
chmod +x /tmp/pkg-scripts/*

PKG_LOCATION="/tmp/pkg/${APPNAME}-${VERSION}.pkg"

# compress the scripts
( cd /tmp/pkg-scripts && find . | cpio -o --format odc --owner 0:80 | gzip -c ) > /tmp/pkg-flat/Scripts
# compress the payload
( cd /tmp/pkg-payload && find . | cpio -o --format odc --owner 0:80 | gzip -c ) > /tmp/pkg-flat/Payload
# create Bom file
( cd /tmp/pkg-payload && ls4mkbom -u 0 -g 80 . ) > /tmp/pkg/.bom
( cd /tmp/pkg-payload && ls4mkbom -u 0 -g 80 .ssh | sed 's/^\./\.\/\.ssh/' ) >> /tmp/pkg/.bom
mkbom -i /tmp/pkg/.bom /tmp/pkg-flat/Bom
# pkg it up!!
( cd /tmp/pkg-flat && xar --compression none -cf "${PKG_LOCATION}" * ) 
echo "osx package has been built: ${PKG_LOCATION}"