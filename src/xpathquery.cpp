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
	string xquery = settings["xquery"];
	string expand = settings["expand"];
	string end = settings["end"];

	if ( filename == "" | xquery == "" ) {
        cout << "Usage: xquery --filename=[fn] --xquery=[xpath query]" << endl;
    	return -1;
	}; // We can only treat XML files and assume those always end on .xml or .psdx

	if ( filename.find(".xml") == -1 && filename.find(".psdx") == -1 ) {
        cout << "  Not an XML file: " << filename << endl;
    	return -1;
	}; // We can only treat XML files and assume those always end on .xml or .psdx
	if ( xquery == "" ) {
        cout << "  No xquery given" << endl;
    	return -1;
	}; // We can only treat XML files and assume those always end on .xml

    // Now - read the file 
	string sep;

    pugi::xml_document doc;
    if ( !doc.load_file(filename.c_str(), (pugi::parse_ws_pcdata)) ) { // pugi::parse_default | 
        cout << "  Failed to load XML file: " << filename << endl;
    	return -1;
    };
	
	// pugi::xpath_node_set toks = doc.select_nodes(xquery);
	pugi::xpath_node_set toks = pugi::xpath_query(xquery.c_str()).evaluate_node_set(doc);

	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xpath_node node = *it;
		pugi::xml_node resnode = node.node();
		pugi::xml_node startnode = resnode;
		
		// print the direct result
		if ( expand != "" ) {
			// expand the result to the enclosing <expand>
 			while ( resnode.name() != expand  && resnode.parent()  ) { // 
				// cout << resnode.name() << endl;
				resnode = resnode.parent();
			};
			// If we fail to get an <s>, revert to <tok>
			if ( resnode.name() != expand ) { resnode = startnode; expand = resnode.name(); }; 
		} else {
			expand = resnode.name();
		};
		resnode.print(std::cout); // , "\t", pugi::format_raw, pugi::encoding_utf8);
		
		if ( end != "" ) {
			pugi::xpath_node tmp = doc.select_single_node(end.c_str());
			pugi::xml_node endnode = tmp.node();
			
			if ( expand != "" ) {
				// expand the result to the enclosing <expand>
				while ( endnode.name() != expand  && resnode.parent()  ) { // 
					// cout << resnode.name() << endl;
					endnode = endnode.parent();
				};
			};
			
			if ( endnode ) { // first make sure we actually find the endnode
				while ( resnode != endnode && resnode.next_sibling(expand.c_str()) ) {
					resnode = resnode.next_sibling(expand.c_str());
					resnode.print(std::cout, "", pugi::format_raw, pugi::encoding_utf8); // , "\t", pugi::format_raw, pugi::encoding_utf8);
				};
			};
		};
		
	};
	
	
}
