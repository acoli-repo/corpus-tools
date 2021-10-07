## Installation

TEITOK installation is done best using the installer script, for which the instructions are given below.

### Linux

The installation on Linux is the typically the most straight-forward, an has been tested on a large variety of Linux 
flavours. Known issues are list on the (Help pages)[http://www.teitok.org/index.php?action=help&id=install].

	curl -O http://www.teitok.org/downloads/install-teitok.pl
	sudo perl install-teitok.pl
	
### Mac

	xcode-select --install
	wget http://www.teitok.org/downloads/install-teitok.pl
	sudo perl install-teitok.pl
	
### Windows

Given that TEITOK heavily relies on the Linux architecture, no Windows installation is available or 
foreseen. To run TEITOK on Windows, it should be run either on a Virtual Machine like (Hyper-V)[https://docs.microsoft.com/en-us/virtualization/hyper-v-on-windows/about/],
or inside something like (Docker)[https://docs.docker.com/desktop/windows/install/]. A complete
yet somewhat outdated installer for TEITOK on Docker can be found on (GitHub)[https://github.com/rahonalab/TEITOK-docker].
