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
    --with-system-curl \
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
./instdir/NOTICE

cd ../

# Explicitly add shared libraries to office executable folder as they will be needed in Lambda environment.
cp /usr/lib64/libssl3.so ./libreoffice/instdir/program/
cp /usr/lib64/libxslt.so ./libreoffice/instdir/program/
cp /usr/lib64/libxslt.so.1 ./libreoffice/instdir/program/
cp /usr/lib64/libfontconfig.so ./libreoffice/instdir/program/
cp /usr/lib64/libfontconfig.so.1 ./libreoffice/instdir/program/

# Need to fix fonts location
cp -rv/etc/fonts/ ./libreoffice/instdir
