# TEITOK TOOLS

This folders contain some tools that make TEITOK work faster and more smoothly. These are
source code files that need to be compiled on the local server

* NeoTagXML is the TEITOK internal POS tagger that tags directly on XML files. It is a rewrite
of the NeoTag tagger, and reads directly from TEITOK settings.xml files to obtain parameter settings.

* NeoTagTrain is the training program to create parameter files for NeoTagXML

* TEITOK-verticalize is a program that creates .vrt files from TEITOK to create a CWB corpus

* XPath-Query is a quick tool to run an XPath query on a collection of XML files

## Compilation instructions

To use these programs, compile them on your server, and either place them in a folder **bin**
inside your **teitok** folder next to your project(s), or place them in /usr/local/bn

These programs only have two dependencies: LBOOST and PUGIXML, where the latter is included
in this folder. How to include LBOOST may depend on your server, but typical compile instructions
are:

g++ -o neotagxml neotag-xml.cpp pugixml.cpp -lboost_system

g++ -o neotagtrain neotag-train.cpp pugixml.cpp -lboost_system

g++ -o tt-verticalize teitok-verticalize.cpp  pugixml.cpp 

g++ -o xpathquery xpath-query.cpp  pugixml.cpp 

