# TEITOK

A web-based framework for creating and distributing textually and linguistically annotated corpora.

More information about the framework can be found on the TEITOK Home Page: http://www.teitok.org

For support and recent developments, there is also a [Google group mailing list](https://groups.google.com/forum/#!forum/teitok) and a [Facebook page](https://www.facebook.com/maartenes) for TEITOK.

## Download

This package is maintained at
[GitLab](https://gitlab.com/maartenes/TEITOK). Issues 
should be submitted there. There is also a fork of the repository on [GitHub](https://github.com/ufal/teitok), which 
is kept synchronised on a daily basis, so the repository can be cloned from there as well.

## License

(c) 2015- Maarten Janssen

This package is [licenced](LICENCE) under the GNU General Public License v3.0.

At this moment, the repository is private to protect users, until security issues have been sufficiently addressed.

## Installation

For the largest part, TEITOK is a PHP/Javascript which only requires a **teitok** folder under the WWW root, with both the **common** folder and one or more project folders inside. The best way to set this up is to clone this Git project to your computer, create the folder teitok and copy the folder **check** to there. On top of that, create a symbolic link called *common* inside the teitok folder that points to the *common* folder in the Git project. That way, TEITOK will always used the latest updates after you update your Git files.

After creating the folder structure, open the **check** folder in your browser. That will open the configuration script, which checks whether all required files are found and accessible. Once all potential problems have been resolved, copy any of default project in the **projects** folder to the teitok folder, renaming it to match your corpus. That should start your (empty) project in TEITOK (with settings already partially tuned to the type of corpus you selected) with some instructions on how to proceed. 

More information about how to customize your TEITOK project can be found on the [TEITOK help page](www.teitok.org/index.php?action=help)

### Dependencies
- [Smarty Template engine](http://www.smarty.net/)
- [Corpus WorkBench](http://cwb.sourceforge.net/) (when using CQP)

The **src** folder contains several programs that make TEITOK work more smoothly, but are optional. These have to be installed locally on the server, with instruction provided in the src folder. Most of those depend on the c++ boost library.
