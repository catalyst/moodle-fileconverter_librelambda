This folder contains the pre-compiled LibreOffice binaries as a compressed archive.
It is uploaded to an AWS bucket as part of the provisioning script.

See the "Libre Office Archive and Compliation" office section of the main plugin README for more information, about this archive.
This section of the project README also explains how to compile LibreOffice yourself.

This folder also contains the scripts to allow you to compile your own version of libre office. The scripts are:
* cli/prereq.sh - Configure the EC2 instance with all the prerequistes required to compile LibreOffice.
* cli/getsource.sh - Get the source code for LibreOffice.
* cli/compile.sh - Compile LibreOffice.
