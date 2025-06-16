# TEITOK

TEITOK comes with a pre-configured Docker container. The container is currently running on Ubuntu 22.04.5 LTS. Docker Pull Command:

	$> docker pull maartenpt/teitok

## Documentation 

- This comes from https://hub.docker.com/r/maartenpt/teitok, see there for updates.

- This is a Dockerized version of the [TEITOK](http://www.teitok.org/) environment. TEITOK is an online environment for creating, managing, searching, and visualizing corpora, in which the corpus files are stored in the [TEI/XML](https://tei-c.org/) format. The main TEITOK distribution can be found on [GitLab](https://gitlab.com/maartenes/TEITOK/). 

- The container provides a fully functional TEITOK environment in a Docker container that can be reached via the browser on the host machine. To run the container correctly you should redirect the Apache port from the container and run it interactively:

		docker run -d -p 8014:80 -it maartenpt/teitok

- you can then access the TEITOK installation in your browser - the container comes with a pre-generated test project:

	- [http://127.0.0.1:8014/teitok/test/index.php](http://127.0.0.1:8014/teitok/test/index.php)

- For generating new projects and managing server-wide settings, use the admin project:

	- [http://127.0.0.1:8014/teitok/shared/index.php](http://127.0.0.1:8014/teitok/shared/index.php)

- The installation has a default super admin user, with the following crudentials: `teitokadmin@localhost/changethis` - which should be changed after setting up the container, especially if the container is made accessible to the outside world.

## Issues

- The original container is shipped as `linux/arm64/v8`, on a household computer, we thus get

	WARNING: The requested image's platform (linux/arm64/v8) does not match the detected host platform (linux/amd64/v4) and no specific platform was requested

- Rebuilt from https://gitlab.com/maartenes/TEITOK.git for linux/amd64/v4.

## Setup

Pull

	$> docker pull maartenpt/teitok



Run in interactive mode

	$> docker run -d -p 8014:80 -it maartenpt/teitok