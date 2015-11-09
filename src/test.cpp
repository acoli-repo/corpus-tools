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


//[code_save_custom_writer
struct xml_string_writer: pugi::xml_writer
{
    std::string result;

    virtual void write(const void* data, size_t size)
    {
        result += std::string(static_cast<const char*>(data), size);
    }
};
//]

std::string node_to_string(pugi::xml_node node)
{
    xml_string_writer writer;
    node.print(writer);

    return writer.result;
}

map <string, string> settings;
string getsetting ( string key );

string getsetting ( string key ) {
	if ( settings.find(key) == settings.end() ) {
		return "";
	} else {
		return settings[key];
	};
};

int treatfile ( string filename ) {

	if ( filename.find(".xml") == -1 ) { return 0; }; // We can only treat XML files and assume those always end on .xml

    // Now - read the file 
	string sep;

    pugi::xml_document doc;
    if (!doc.load_file(filename.c_str())) {
        cout << "  Failed to load XML file " << filename << endl;
    	return -1;
    };

	vector<string>::iterator cait;
	string fld;
	    
    // Go through the toks
	pugi::xpath_node_set toks = doc.select_nodes("//tok");

	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xpath_node node = *it;

		int nodestart = node.node().offset_debug();
		string nodetxt = node_to_string(node.node());
		int nodelength = nodetxt.length();
		
		cout << node.node().attribute("id").value() << "\t" << nodestart << "\t" << nodelength <<  endl;
					
	}
};


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

	string sep;
	if ( getsetting("filename") != "" ) { 
		treatfile ( settings["filename"] );   
	};
	
}
