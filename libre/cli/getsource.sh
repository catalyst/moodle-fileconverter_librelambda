#!/usr/bin/env bash

# This script gets the source code of LibreOffice.

curl -L https://github.com/LibreOffice/core/archive/libreoffice-6.2.1.2.tar.gz | tar -xz
mv core-libreoffice-6.2.1.2 libreoffice

# See https://ask.libreoffice.org/en/question/72766/sourcesver-missing-while-compiling-from-source/
echo "lo_sources_ver=6.2.1.2" >> libreoffice/sources.ver
