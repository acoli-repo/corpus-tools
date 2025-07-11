# TEITOK

This is a Dockerized version of the [TEITOK](http://www.teitok.org/) environment. TEITOK is an online environment for creating, managing, searching, and visualizing corpora, in which the corpus files are stored in the [TEI/XML](https://tei-c.org/) format.  

In this folder we provide a way to run TEITOK inside a Docker image, which is a virtualised
environment inside your computer. Using Docker is recommended in many organisations since it keeps 
your other data separated from your TEITOK instance. It is also currently necessary if you
want to run TEITOK on a Mac or Windows machine: Windows was never supported, and 
TEITOK can be run directly on a Mac, but due to the heavy security in the latest
versions of MacOS, it is no longer trivial to install, and the use of Docker is strongly 
recommended. The Dockerfile and its documentation are directly based on Maarten Jansen's [TEITOK Dockerization](https://hub.docker.com/r/maartenpt/teitok), with minor modifications only. The main TEITOK distribution can be found on [GitLab](https://gitlab.com/maartenes/TEITOK/). In case you encounter any difficulties, it is probably safest to return to the version from https://hub.docker.com/r/maartenpt/teitok.

> Note: At the time of writing (2025-06-16), the original container is shipped as `linux/arm64/v8` (designed for mobile processors, as use in tablets or, for example, RaspBerry Pi), on a household computer, we thus get `WARNING: The requested image's platform (linux/arm64/v8) does not match the detected host platform (linux/amd64/v4) and no specific platform was requested`. The current image has been built on a `linux/amd64/v4`.

- Pulling it

	$> docker pull chiarcos/teitok-docker

- The container provides a fully functional TEITOK environment in a Docker container that can be reached via the browser on the host machine. To run the container correctly you should redirect the Apache port from the container and run it interactively so that it
does not clash with any local web server:

		docker run -d -p 8014:80 -it chiarcos/teitok-docker

- The container comes with a pre-generated test project, and with that we have a local container running Ubuntu that runs TEITOK inside. You can then access the TEITOK installation in your browser via the following URL:

	- [http://127.0.0.1:8014/teitok/test/index.php](http://127.0.0.1:8014/teitok/test/index.php)

- For generating new projects and managing server-wide settings, use the admin project:

	- [http://127.0.0.1:8014/teitok/shared/index.php](http://127.0.0.1:8014/teitok/shared/index.php)

- Since inside the Docker container TEITOK is installed in a non-interactive way, the installation has a default super admin user, see Sect. "Using it" below. If you plan to publish the content of your container, these should be changed after setting up the container.

You now have a fully working TEITOK environment in a Docker container, where you can create TEITOK projects in `Admin > server-wide settings > Create new project`.

## Disclaimer

The Dockerized TEITOK is meant to be used at a local server without public access for testing or as a portable environment for use in places where no internet access is available. However, the Docker image is a snapshot of the technology at a specific point in time, and for a public access ppint, you should better use a native installation with regular maintenance.

However, it is possible to create such a public instance from your image, as it is easy to later copy the files or entire projects to a web server where TEITOK is installed. For building instructions, see http://teitok.corpuswiki.org/.

## Building it

To use TEITOK in a docker instance, the first thing to do is to install Docker on your computer. The easiest way is to install [Docker Desktop](https://docs.docker.com/desktop/). 

Then, build with

	$> make build

This will build the image, and run the TEITOK installer inside the container. Except from setting Ubuntu to a fixed version (rather than `:latest`), and building it on an `linux/amd64/v4` environment, this is largely unchanged from Maarten's original Dockerfile. However, it uses a build script downloaded at build time and at some stage in the future, this may no longer work with Ubuntu 22.04. The Dockerfile needs to be updated, then.

## Using it (vanilla configuration)

Pulling it

	$> docker pull chiarcos/teitok-docker

Starting it

	$> docker run -d -p 8014:80 -it chiarcos/teitok-docker

Go to your browser and open [http://127.0.0.1:8014/teitok/shared/index.php](http://127.0.0.1:8014/teitok/shared/index.php).

The container is preconfigured:
- It can load TEI/XML files.
- It can load associated facsimiles (`*.jpg`). 
- It can load TEITOK files (TEI/XML with `<tok>` elements, using the attribute `@lemma`).
- It is pre-configured for using CQP.

In order to work with your data, you first need to upload some data. For doing so, select "Login" on the welcome page (Fig.1) and authentificate yourself, with the following crudentials:

	teitokadmin@localhost/changethis

<table style="text-align: center;">
	<tr>
		<td>
			<img src="img/login-page.png" height="225"/>
		</td>
	</tr>
	<tr><td>Fig. 1 Welcome page</td>
	</tr>
</table>

You should now see the Admin Functions as in Fig. 2. If you click on "upload/manage files", then "TEI/XML", you can upload your TEI/XML and TEITOK/XML files (incl. bulk upload). If the upload worked, and you re-load the page, you should see an interactive link for your file. If there was a problem (e.g., XML validity errors, unsupported XML elements, misplaced attributes), you will see the file name, but grayed out. It is fairly hard to figure out what went wrong there. Talk to your course instructors and/or the providers of this Docker container. If this is not possible, you can also reach out to the TEITOK developer(s), see [the TEITOK GitLab repository](https://gitlab.com/maartenes/TEITOK/), but make sure to tell them how you got to this point and to provide them with a pointer to the Dockerfile and a sample of your data.

<table style="text-align: center;">
	<tr>
		<td>
			<img src="img/admin-functions.png" height="250"/>
		</td>
	</tr>
	<tr><td>Fig. 2 Admin functions</td>
	</tr>
</table>

After uploading files, make sure to run "(re)generate the CQP corpus" from the Admin Functions (Fig. 2) and to follow the dialog. You may need to wait for several minutes, depending on the size of your corpus. 

> Note: As you run this in a Docker container, everything will be wiped clean as soon as you shut down the container and start it, again. You need to upload your XML files again, and you need to generate the CQP corpus, again. We plan to improve this. Also, if you reconfigured your admin login and password, this will be reset to the original values.

In your menu to the left, you will now also see the new option `XML Files`. This will bring you to the document view of your files. Alternatively, you can also go to [http://127.0.0.1:8014/teitok/shared/index.php?action=files](http://127.0.0.1:8014/teitok/shared/index.php?action=files).

Furthermore, you can reach the corpus search interface under [http://127.0.0.1:8014/teitok/shared/index.php?action=cqp](http://127.0.0.1:8014/teitok/shared/index.php?action=files).

A TEITOK corpus is effectively just an HTML page with links to both your XML files (for document view) and the CQP search interface (for corpus view). If configured correctly, TEITOK allows you to switch between both views.

## History

- teitok-docker:0.1 this is [Maarten Jansen's original Dockerfile](https://hub.docker.com/r/maartenpt/teitok) from late 2024, adjusted to use Ubuntu:22.04
- teitok-docker:0.2 custom configuration, adapted from the Montenegro corpus
- teitok-docker:0.21 extended CoNLL-U configuration
- teitok-docker:0.21 extended CoNLL-U configuration, --volume .:/shared (optional)

## TODO

- No full CoNLL-U support, yet.
