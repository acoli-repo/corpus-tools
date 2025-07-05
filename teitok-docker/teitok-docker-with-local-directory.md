# TEITOK-Docker

## Prep

- Setup Docker on your system
- Start your local Docker service, e.g., DockerDesktop
- Pull our TEITOK container

	```$> docker pull chiarcos/teitok-docker```

## Run TEITOK in a box

On your terminal (command line), run


```
$> docker run -d -p 8014:80 chiarcos/teitok-docker
```

Open the following URL in your browser:

- [http://127.0.0.1:8014/teitok/shared/index.php](http://127.0.0.1:8014/teitok/shared/index.php)

> Note: `127.0.0.1` (aka `localhost`) is your computer, the number following `:` (here `8014`) must be the same as the number you provided as `-p` argument (i.e., between `-p` and `:80` when running the container.

**NOTE**: In this configuration, you will loose all your information when you close the container. See "Run TEITOK with a local folder" below.

You can see your running containers with

```
docker ps
```

In the first column, you see an id, if you want to terminate a running container (these eat up some resources ...), take that number (say `e7e48c07856c`) and `kill` the container:

```
docker kill e7e48c07856c
```

## RUN TEITOK with a local folder (advanced)

Normally, when you close TEITOK and start it again, all your data is lost. If you want to have a "memory" and build a releasable corpus / digital edition, you need to store your data outside the container (on your computer) and access this data.

TEITOK-Docker has been configured to allow that. However, remember that your Container actually behaves like it is a separate computer. That means that it does not know anything about users of the host system (your actual computer). But access rights are granted per user (groups), and in order for you to work seamlessly with your data, you may need to manually change permissions on TEITOK content in your host system. If you don't, you should be able to read all information deposited by TEITOK, but you may not add new files or change any of these files by hand.

- Create a new folder and enter this directory, e.g.

	```
	$> mkdir teitok
	$> cd teitok
	```

- Start TEITOK-Docker, and allow it to use from your local directory (here `.`) as `/shared`

	```
	$> docker run -d -p 8014:80 --volume .:/shared chiarcos/teitok-docker
	```

	Alternatively, you can also point to another directory, say `some-local-path` (replace with your actual path):

	```
	$> docker run -d -p 8014:80 --volume some-local-path:/shared chiarcos/teitok-docker
	```

	TEITOK-Docker will install its vanilla configuration into this directory (unless it already contains a TEITOK configuration). This includes configuration files and data for the `shared` configuration.

	The next time you start TEITOK-Docker and provide the same path, it should start working at the point where you left it.

- After you uploaded some data to TEITOK, it should show up in this directory, e.g., for TEITOK-XML files in the sub-directory `xmlfiles`. You should be able to read this information, but you may not directly modify it or add to it. For this, you need write access.

- To write to the folder that contains your TEITOK configuration (here, `teitok`), you can set it to writeable for everyone. You might need administrator rights to be able to do so. You need to do that in your host system, see [here](https://learn.microsoft.com/en-us/windows/security/identity-protection/access-control/access-control#permissions) for Windows, and [here](https://www.macinstruct.com/tutorials/how-to-set-file-permissions-on-a-mac/) for MacOS. Under Linux (and the MacOS Terminal), you can navigate to the directory and run

	```
	$> chmod -R a+rw *
	```

	If you get an error message that this is not allowed, try

	```
	$> sudo chmod -R a+rw *
	```

	After that, you can just add (and remove) files from your TEITOK-Docker by putting them into the right directories, e.g., TEITOK-XML files into `teitok/xmlfiles`, etc.

- If you are done with configuring your corpus/digital edition, you can create a zip archive from the folger that contains your TEITOK installation. Give this to the server admin of your choice (together with a pointer to this Docker container and to the [TEITOK online documentation](http://www.teitok.org)), and they should be able to put it online for you.