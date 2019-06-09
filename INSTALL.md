## Installation

TEITOK can be installed in a number of different ways, but below is our own installation flow. Given that there is a number of steps that require manual intervention, there is no install script that takes care of this, but the number of steps is relatively limited.

For people that prefer a Docker installation, there is a docker project by Luigi Talamo [here](https://github.com/rahonalab/TEITOK-docker)

* Register as a user at [GitLab](https://gitlab.com/users/sign_in)
* Ask to be added to the TEITOK project, message your GitLab username on the [Facebook page](https://www.facebook.com/maartenes) 
* Add an SSH key to GitLab from the server where you want to install TEITOK [here](https://gitlab.com/profile/keys)
* From the command line, go to the folder where you want to download Git repositories (say ~/Git) and do `git clone git@gitlab.com:maartenes/TEITOK.git`
* If you have not installed Smarty, clone that as well: `git clone https://github.com/smarty-php/smarty.git`
* Copy the Smarty classes to `sudo cp -R ~/Git/smarty/libs /usr/local/share/smarty`
* Go to the root of Apache (/var/www/html, /Library/WebServer/Documents, etc.) and create a folder `mkdir teitok`
* Enter the new folder and link common from the Git folder, using a command like `ln -s ~/Git/TEITOK/common /var/www/html/teitok/common`
* Link the Scripts folder from the Git folder, using a command like `ln -s ~/Git/TEITOK/Scripts /var/www/html/teitok/Scripts`
* Copy the check folder to your TEITOK home: `sudo cp -R ~/Git/TEITOK/check .`
* In your browser (preferably Chrome or Firefox) go to the check folder, say [http://127.0.0.1/teitok/check](http://127.0.0.1/teitok/check)
* Follow the instructions on that page until all crucial errors disappear (install LibXML, PHP, CWB, etc.)
* Choose the most appropriate default project (Learner Corpus, Historical, Oral, Minimal) to the teitok home with the desired project name (say myproject): `sudo cp -R ~/Git/TEITOK/project/default-hist myproject`
* Make sure all files are writable for the apache user (apache,www,www-data,_www): `sudo chown -R apache:apache /var/www/html/teitok`
* In your browser go to your new project, say [http://127.0.0.1/teitok/myproject](http://127.0.0.1/teitok/myproject)
