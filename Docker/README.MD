## TEITOK Docker

In this folder we provide a way to run TEITOK inside a Docker image, which is a virtualised
environment inside your computer. Using Docker is recommended in many organisations since it keeps 
your other data separated from your TEITOK instance. It is also currently necessary if you
want to run TEITOK on a Mac or Windows machine: Windows was never supported, and 
TEITOK can be run directly on a Mac, but due to the heavy security in the latest
versions of MacOS, it is no longer trivial to install, and the use of Docker is strongly 
recommended.

To use TEITOK in a docker instance, the first thing to do is to install Docker on your 
computer. The easiest way is to install [Docker Desktop](https://docs.docker.com/desktop/).

Once you have Docker installed, download the Dockfile from this Git and place it in a 
directory on your computer. Then go in the Terminal to that folder, and build the docker image:

``docker build -t teitok-docker .``

This will build the image, and run the TEITOK installer inside the container. Instead of building
it locally, you can also clone it from the [Docker Hub](https://hub.docker.com/repository/docker/maartenpt/teitok/general)

Now we have
to start that container and expose the port inside the container to a different port outside so that it
does not clash with any local web server:

``docker run -d -p 8014:80 -it teitok-docker``

And with that we have a local container running Ubuntu that runs TEITOK inside, and which we can 
access via the browser via the following URL:

http://127.0.0.1:8014/teitok/shared/index.php

Since inside the Docker container TEITOK is installed in a non-interactive way, there is
a default username and password in the shared project:

``teitokadmin@localhost/changethis``

You now have a fully working TEITOK environment in a Docker container, where you can create
TEITOK projects in `Admin > server-wide settings > Create new project`

The Dockerized TEITOK can be used as a publicly accessible server or as a local testing or
portable environment for use in places where no internet access is available. When used
for local development, it is easy to later copy the files or entire projects to a 
web server where TEITOK is installed.