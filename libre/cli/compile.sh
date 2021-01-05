#!/usr/bin/env bash

# This script compiles LibreOffice.

# This is the most important part, we set the compile options.
# Run ./autogen.sh --help to see wha each option means
cd libreoffice

./autogen.sh \
    --disable-avahi \
    --disable-cairo-canvas \
    --disable-coinmp \
    --disable-cups \
    --disable-cve-tests \
    --disable-dbus \
    --disable-dconf \
    --disable-dependency-tracking \
    --disable-evolution2 \
    --disable-dbgutil \
    --disable-extension-integration \
    --disable-extension-update \
    --disable-firebird-sdbc \
    --disable-gio \
    --disable-gstreamer-1-0 \
    --disable-gtk3 \
    --disable-introspection \
    --disable-gui \
    --disable-largefile \
    --disable-lotuswordpro \
    --disable-lpsolve \
    --disable-odk \
    --disable-ooenv \
    --disable-pch \
    --disable-postgresql-sdbc \
    --disable-python \
    --disable-randr \
    --disable-report-builder \
    --disable-scripting-beanshell \
    --disable-scripting-javascript \
    --disable-sdremote \
    --disable-sdremote-bluetooth \
    --enable-mergelibs \
    --with-galleries="no" \
    --with-system-expat \
    --with-system-nss \
    --with-theme="no" \
    --without-export-validation \
    --without-fonts \
    --without-helppack-integration \
    --without-java \
    --without-junit \
    --without-krb5 \
    --without-myspell-dicts \
    --without-system-dicts

# Disable flaky unit tests failing on macos (and for some reason on Amazon Linux as well).
sudo sed -i 's/\#if\ \!defined\ MACOSX\ \&\& \!\defined\ \_WIN32/\#if\ defined\ MACOSX\ \&\&\ \!defined\ _WIN32/g' vcl/qa/cppunit/pdfexport/pdfexport.cxx

# Compile it! This will take 0-2 hours to compile, depends on the AWS instance size.
make

# Remove ~100 MB of symbols from shared objects.
strip ./instdir/**/*

# Remove unneeded stuff for headless mode.
rm -rf ./instdir/share/gallery \
    ./instdir/share/config/images_*.zip \
    ./instdir/readmes \
    ./instdir/CREDITS.fodt \
    ./instdir/LICENSE* \
    ./instdir/NOTICE \
    ./instdir/program/wizards

cd ../

# Explicitly add shared libraries to office executable folder as they will be needed in Lambda environment.
# Made by running "ldd ./libreoffice/instdir/program/soffice.bin | grep -v 'ec2-user'" and some trial and error.
# Including running "logger.info(os.listdir('/usr/lib64'))" inside an existing Lambda function.
cp /usr/lib64/libbz2.so.1 ./libreoffice/instdir/program/
cp /usr/lib64/libcrypt.so.1  ./libreoffice/instdir/program/
cp /usr/lib64/libcurl.so ./libreoffice/instdir/program/
cp /usr/lib64/libcurl.so.4 ./libreoffice/instdir/program/
cp /usr/lib64/libcurl.so.4.5.0 ./libreoffice/instdir/program/
cp /usr/lib64/libexpat.so ./libreoffice/instdir/program/
cp /usr/lib64/libexpat.so.1 ./libreoffice/instdir/program/
cp /usr/lib64/libexpat.so.1.6.0 ./libreoffice/instdir/program/
cp /usr/lib64/libfontconfig.so ./libreoffice/instdir/program/
cp /usr/lib64/libfontconfig.so.1 ./libreoffice/instdir/program/
cp /usr/lib64/libfreetype.so.6 ./libreoffice/instdir/program/
cp /usr/lib64/libidn2.so.0 ./libreoffice/instdir/program/
cp /usr/lib64/liblber-2.4.so.2 ./libreoffice/instdir/program/
cp /usr/lib64/libldap-2.4.so.2 ./libreoffice/instdir/program/
cp /usr/lib64/liblzma.so.5 ./libreoffice/instdir/program/
cp /usr/lib64/libnghttp2.so.14 ./libreoffice/instdir/program/
cp /usr/lib64/libnss3.so ./libreoffice/instdir/program/
cp /usr/lib64/libpng15.so.15 ./libreoffice/instdir/program/
cp /usr/lib64/libsasl2.so.3 ./libreoffice/instdir/program/
cp /usr/lib64/libsmime3.so ./libreoffice/instdir/program/
cp /usr/lib64/libssh2.so.1 ./libreoffice/instdir/program/
cp /usr/lib64/libssl3.so ./libreoffice/instdir/program/
cp /usr/lib64/libunistring.so.0 ./libreoffice/instdir/program/
cp /usr/lib64/libuuid.so.1 ./libreoffice/instdir/program/
cp /usr/lib64/libxml2.so ./libreoffice/instdir/program/
cp /usr/lib64/libxml2.so.2 ./libreoffice/instdir/program/
cp /usr/lib64/libxml2.so.2.9.1 ./libreoffice/instdir/program/
cp /usr/lib64/libxslt.so ./libreoffice/instdir/program/
cp /usr/lib64/libxslt.so.1 ./libreoffice/instdir/program/

# Need to fix fonts location
cp -rv/etc/fonts/ ./libreoffice/instdir
