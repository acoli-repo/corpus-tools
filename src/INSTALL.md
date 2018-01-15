# TEITOK TOOLS

This folders contain some tools that make TEITOK work faster and more smoothly. These are
source code files that need to be compiled on the local server

* NeoTagXML is the TEITOK internal POS tagger that tags directly on XML files. It is a rewrite
of the NeoTag tagger, and reads directly from TEITOK settings.xml files to obtain parameter settings.

* NeoTagTrain is the training program to create parameter files for NeoTagXML

* TEITOK-verticalize is a program that creates .vrt files from TEITOK to create a CWB corpus

* XPath-Query is a quick tool to run an XPath query on a collection of XML files

## Compilation instructions

To use these programs, compile them on your server, and either place them in /usr/local/bin, 
or place them somewhere you want them to be and indicate in your 
settings.xml where the executable is located.

These programs only have two dependencies: [http://www.boost.org/](BOOST) and [http://pugixml.org/](PUGIXML), 
where the latter is included in this folder. 
How to include BOOST may depend on your server, but typical compile instructions are:

sudo g++ -o /usr/local/bin/neotagxml neotagxml.cpp pugixml.cpp -lboost_system

sudo g++ -o /usr/local/bin/neotagtrain neotagtrain.cpp pugixml.cpp -lboost_system

sudo g++ -o /usr/local/bin/tt-cwb-encode tt-cwb-encode.cpp pugixml.cpp -lboost_system

sudo g++ -o /usr/local/bin/tt-cwb-xidx tt-cwb-xidx.cpp pugixml.cpp -lboost_system

sudo g++ -o /usr/local/bin/tt-cqp tt-cqp.cpp pugixml.cpp -lboost_system

