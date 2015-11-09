#include <boost/algorithm/string.hpp>
#include "pugixml.hpp"
#include <iostream>
#include <string.h>
#include <stdio.h>
#include <fstream>
#include <map>
#include <vector>
#include <dirent.h>
#include <sys/stat.h>

using namespace std;
using namespace boost;

// Where we store the settings
map <string, string> settings;

int main(int argc, char *argv[])
{

	// Read in all the command-line arguments
	for ( int i=1; i< argc; ++i ) {
		
		string argm = argv[i];
		
		if ( argm.substr(0,2) == "--" ) {
		
			int spacepos = argm.find("=");
			
			if ( spacepos == -1 ) {
				string akey = argm.substr(2);
				settings[akey] = "1";
			} else {
				string akey = argm.substr(2,spacepos-2);
				string aval = argm.substr(spacepos+1);
				settings[akey] = aval;
			};

		};
		
	};

	string filename = settings["filename"];
	string begin = settings["begin"];
	string end = settings["end"];
	string expand = settings["expand"];
	string context = settings["context"];
	
	bool debug;
	string tmp = settings["debug"];
	if ( tmp != "" ) { 
		cout << "running in debug mode" << endl;
		debug = true; 
	};

	if ( filename.find(".xml") == -1 ) {
        cout << "  Not an XML file: " << filename << endl;
    	return -1;
	}; // We can only treat XML files and assume those always end on .xml
	if ( begin == "" ) {
        cout << "  No being node given" << endl;
    	return -1;
	}; 
	if ( expand == "" ) {
		expand = "tok";
		if ( debug ) { cout << "expand = " << expand << endl; };
	};
	
    // Now - read the file 
	string sep;

    pugi::xml_document doc;
    if ( !doc.load_file(filename.c_str(), ( pugi::parse_ws_pcdata )) ) { // pugi::parse_default | 
        cout << "  Failed to load XML file: " << filename << endl;
    	return -1;
    };

	// find the startnode and expand to the desired level
	pugi::xml_node beginnode;
	if ( end != "" ) {
		string beginquery = "//*[@id=\""+begin+"\"]";
		if ( debug ) { cout << "begin = " << beginquery << endl; };
		pugi::xpath_node tmp = doc.select_single_node(beginquery.c_str());
		beginnode = tmp.node();
		if ( debug ) { beginnode.print(std::cout); };
		
		while ( beginnode.name() != expand  && beginnode.parent()  ) { // 
			beginnode = beginnode.parent();
			if ( debug ) { beginnode.print(std::cout); };
		};
	};

	// find the endnode and expand to the desired level
	pugi::xml_node endnode;
	if ( end != "" ) {
		string endquery = "//*[@id=\""+end+"\"]";
		if ( debug ) { cout << "end = " << endquery << endl; };
		pugi::xpath_node tmp = doc.select_single_node(endquery.c_str());
		endnode = tmp.node();
		if ( debug ) { endnode.print(std::cout); };
		
		while ( endnode.name() != expand  && endnode.parent()  ) { // 
			endnode = endnode.parent();
			if ( debug ) { endnode.print(std::cout); };
		};
	};
	
	bool inside = false;
	string xquery = "//"+expand;
	pugi::xpath_node_set toks = pugi::xpath_query(xquery.c_str()).evaluate_node_set(doc);

	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xpath_node node = *it;
		pugi::xml_node resnode = node.node();
		pugi::xml_node startnode = resnode;
		
		if ( debug ) { cout << resnode.attribute("id").value() << endl; };
		
		if ( inside ) {
			// resnode.print(std::cout);
			resnode.print(std::cout, "", pugi::format_raw, pugi::encoding_utf8); 
			if ( resnode == endnode ) {
				// Found the end, so stop
				return -1;
			};
		} else if ( resnode == beginnode ) {
			inside = true;
			// resnode.print(std::cout);
			resnode.print(std::cout, "", pugi::format_raw, pugi::encoding_utf8); 
			if ( resnode == endnode ) {
				// Found the end, so stop
				return -1;
			};
		} else {
			if ( debug ) { cout << "not yet the beginnode..." << endl; };
		};
	};	
}
