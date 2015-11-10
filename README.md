# TEITOK

A web-based framework for creating and distributing textually and linguistically annotated corpora.

More information about the framework can be found on the TEITOK Home Page: http://teitok.corpuswiki.org

## Download

This package is maintained at
[GitLab](https://gitlab.com/maartenes/TEITOK). Issues and pull requests
should be submitted there.

## License

Copyright 2015 Maarten Janssen

This package is free to use for non-commercial purposes.

At this moment, the repository is private to protect users, until security issues has been sufficiently taken into account.

## Installation

For the largest part, TEITOK is a PHP/Javascript system that will run by merely creating a folder containing both the **common** folder and project folder(s), where the latter are under the Apache root. 

To create project folder(s), copy the **myproject** empty project, and open it in the browser, which will do a configuration check. If there are no crucial errors, rename index-off.php to index.php in that folder (renamed to the name of your project). 

### Dependencies
- [Smarty Template engine](http://www.smarty.net/)
- [Corpus WorkBench](http://cwb.sourceforge.net/) (when using CQP)

The **src** folder contains several programs that make TEITOK work more smoothly, but are optional. These have to be installed locally on the server, with instruction provided in the src folder. Most of those depend on the c++ boost library.
