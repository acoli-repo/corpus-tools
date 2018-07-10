# TEITOK TOOLS

This folders contain some tools that make TEITOK work faster and more smoothly. These are
source code files that need to be compiled on the local server

* NeoTagXML is the TEITOK internal POS tagger that tags directly on XML files. It is a rewrite
of the NeoTag tagger, and reads directly from TEITOK settings.xml files to obtain parameter settings.

* NeoTagTrain is the training program to create parameter files for NeoTagXML

* TEITOK-verticalize is a program that creates .vrt files from TEITOK that can be used for CWB or NoSketchEngine - (deprecated)

* XPath-Query is a quick tool to run an XPath query on a collection of XML files - (deprecated)

## Compilation instructions

To use these programs, compile them on your server, and either place them in /usr/local/bin, 
or place them somewhere you want them to be and indicate in your 
settings.xml where the executable is located.

These programs only have two dependencies: [http://pugixml.org/](PUGIXML), and if you have an older
version of C++ also [http://www.boost.org/](BOOST). So depending on your C++ version, you should use either
the C++11 method or the BOOST method below.

TT-CQP can either use BOOST or C++11 with a c++ version > 4.9. You can compile it with either of these commands:

### Compile with C++ > 4.9

sudo g++ -std=c++11 -o /usr/local/bin/neotagxml neotagxml.cpp pugixml.cpp functions-c11.cpp

sudo g++ -std=c++11 -o /usr/local/bin/neotagtrain neotagtrain.cpp pugixml.cpp functions-c11.cpp

sudo g++ -std=c++11 -o /usr/local/bin/tt-cwb-encode tt-cwb-encode.cpp pugixml.cpp functions-c11.cpp

sudo g++ -std=c++11 -o /usr/local/bin/tt-cwb-xidx tt-cwb-xidx.cpp pugixml.cpp functions-c11.cpp

sudo g++ -std=c++11 -o /usr/local/bin/tt-cqp tt-cqp.cpp pugixml.cpp functions-c11.cpp


### Compile with BOOST

sudo g++ -o /usr/local/bin/neotagxml neotagxml.cpp pugixml.cpp functions-boost.cpp -lboost_system

sudo g++ -o /usr/local/bin/neotagtrain neotagtrain.cpp pugixml.cpp functions-boost.cpp -lboost_system

sudo g++ -o /usr/local/bin/tt-cwb-encode tt-cwb-encode.cpp pugixml.cpp functions-boost.cpp -lboost_system

sudo g++ -o /usr/local/bin/tt-cwb-xidx tt-cwb-xidx.cpp pugixml.cpp functions-boost.cpp -lboost_system

sudo g++ -o /usr/local/bin/tt-cqp tt-cqp.cpp pugixml.cpp functions-boost.cpp -lboost_system -lboost_regex
