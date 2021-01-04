#!/usr/bin/env bash

# This script installs all the required prerequisite to allow you to compile your own version of LibreOffice.

# See https://stackoverflow.com/questions/2499794/how-to-fix-a-locale-setting-warning-from-perl
export LC_CTYPE=en_US.UTF-8
export LC_ALL=en_US.UTF-8

# Enable required packages from the amazon-linux-extras repo
sudo amazon-linux-extras enable python3.8
sudo amazon-linux-extras enable java-openjdk11

# Install base packages required for compilation.
sudo yum-config-manager --enable epel
sudo yum clean metadata
sudo yum install -y \
    autoconf \
    expat-devel \
    expat-devel.x86_64 \
    fontconfig-devel \
    gmp-devel \
    google-crosextra-caladea-fonts \
    google-crosextra-carlito-fonts \
    icu \
    libcurl-devel \
    liberation-sans-fonts \
    liberation-serif-fonts \
    libffi-devel \
    libICE-devel \
    libicu-devel \
    libmpc-devel \
    libpng-devel \
    libSM-devel \
    libX11-devel \
    libXext-devel \
    libXrender-devel \
    libxslt-devel \
    mesa-libGL-devel \
    mesa-libGLU-devel \
    mpfr-devel \
    nasm \
    nspr-devel \
    nss-devel \
    openssl-devel \
    perl-Digest-MD5 \
    python38 \
    python38-devel \
    java-11-openjdk

sudo yum groupinstall -y "Development Tools"

# Install liblangtag (not available in Amazon Linux or EPEL repos).
# Enabling repository sourced from: https://unix.stackexchange.com/questions/433046/how-do-i-enable-centos-repositories-on-rhel-red-hat
sudo touch /etc/yum.repos.d/centos.repo
echo "[centos]" | sudo tee -a /etc/yum.repos.d/centos.repo
echo "name=CentOS-7" | sudo tee -a /etc/yum.repos.d/centos.repo
echo "baseurl=http://ftp.heanet.ie/pub/centos/7/os/x86_64/" | sudo tee -a /etc/yum.repos.d/centos.repo
echo "enabled=1" | sudo tee -a /etc/yum.repos.d/centos.repo
echo "gpgcheck=1" | sudo tee -a /etc/yum.repos.d/centos.repo
echo "gpgkey=http://ftp.heanet.ie/pub/centos/7/os/x86_64/RPM-GPG-KEY-CentOS-7" | sudo tee -a /etc/yum.repos.d/centos.repo

yum repolist
sudo yum install -y liblangtag
sudo cp -r /usr/share/liblangtag /usr/local/share/liblangtag/

# Install required version of gperf
curl -L http://ftp.gnu.org/pub/gnu/gperf/gperf-3.1.tar.gz | tar -xz
cd gperf-3.1
./configure
make
make check
sudo make install
cd ../

# Install required version of flex
curl -L  https://github.com/westes/flex/files/981163/flex-2.6.4.tar.gz | tar -xz
cd flex-2.6.4
./autogen.sh
./configure
make
sudo make install
cd ../
