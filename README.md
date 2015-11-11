# TEITOK

A web-based framework for creating and distributing textually and linguistically annotated corpora.

More information about the framework can be found on the TEITOK Home Page: http://teitok.corpuswiki.org/site

## Download

This package is maintained at
[GitLab](https://gitlab.com/maartenes/TEITOK). Issues and pull requests
should be submitted there.

## License

Copyright 2015 Maarten Janssen

This package is free to use for non-commercial purposes.

At this moment, the repository is private to protect users, until security issues has been sufficiently taken into account.

## Installation

For the largest part, TEITOK is a PHP/Javascript which only requires a **teitok** folder under the WWW root, with both the **common** folder and one or more project folders inside. The best way to set this up is to clone this Git project to your computer, create the folder teitok and copy the **myproject** to there. On top of that, create a symbolic link called *common* inside the teitok folder that points to the *common* folder in the Git project. That way, any update to TEITOK will automatically work.

After creating the folder structure, rename *myproject* to the name of your project, and open it in your browser. That will open the configuration script, which checks whether all required files are found and accessible. Once all potential problems have been resolved, move the *index-off.php* file in your project folder to *index.php* and follow the instructions on the home page. More information about how to customize your TEITOK project can be found on the [TEITOK help page](teitok.corpuswiki.org/site/index.php?action=help)

### Dependencies
- [Smarty Template engine](http://www.smarty.net/)
- [Corpus WorkBench](http://cwb.sourceforge.net/) (when using CQP)

The **src** folder contains several programs that make TEITOK work more smoothly, but are optional. These have to be installed locally on the server, with instruction provided in the src folder. Most of those depend on the c++ boost library.
