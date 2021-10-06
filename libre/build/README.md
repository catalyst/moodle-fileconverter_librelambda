# libreoffice-amazon-lambda-build

Dockerfile and support files for building headless libreoffice capable of running in amazon lambda environment

## Versions

This build for the libreoffice-6.4.7.2 running in the Amazon v2 containers.

I don't believe there will be another LibreOffice 6 release. Amazon 2 image is based on Centos 7
which proved challenging enough to make me not even consider trying with LibreOffice 7. I'd say this is
the best we can do at this moment.

## Build

    wget https://download.documentfoundation.org/libreoffice/src/6.4.7/libreoffice-6.4.7.2.tar.xz

    docker build -t lo-build .
    # OR for arm64/aarch64 (graviton):
    docker build -t lo-build --build-arg ARCH=aarch64 .
    docker run -it --rm lo-build bash

    (in the container)
    make
    # OR for arm64/aarch64 (graviton):
    CPPFLAGS="-DPNG_ARM_NEON_OPT=0" make
    strip instdir/program/*
    tar -cf /lo-instdir.tar instdir

    (in the host shell)
    docker ps
    docker cp <container id>:/lo-instdir.tar .

Now you can leave the container, and maybe remove unneeded things from lo-instdir.tar
(share/gallery,template,fonts/truetype/EmojiOneColor-SVGinOT.ttf...)

## If something goes wrong

    docker run -it --rm --cap-add=SYS_PTRACE --security-opt seccomp=unconfined lo-build bash

If you are rebuilding the image, you can copy tarballs from the running container's
`<build dir>/external/tarballs/` to the `tarballs` directory. That will save you downloading each time you start new container.
