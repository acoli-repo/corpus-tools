#include <boost/algorithm/string.hpp>
#include "pugixml.hpp"
#include <iostream>
#include <string.h>
#include <stdio.h>
#include <fstream>
#include <map>
#include <vector>

using namespace std;
using namespace boost;

map <string, string> settings;
string getsetting ( string key );

string getsetting ( string key ) {
	if ( settings.find(key) == settings.end() ) {
		return "";
	} else {
		return settings[key];
	};
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

	string filename;
	string fields;

	if ( getsetting("filename") != "" ) { 
		filename = settings["filename"];
	} else { 
		filename  = argv[argc];
	};

	string folder;
	if ( getsetting("folder") != "" ) { 
		folder = settings["folder"];
	} else { 
		cout << "No folder indicated";
		return -1;	
	};

	
	// Read the settings file to see what we need to export
	
	string resourcefile = folder + "/Resources/settings.xml";
    
    pugi::xml_document xmlsettings;
    if (!xmlsettings.load_file(resourcefile.c_str())) {
        cout << "  Failed to load settings file " << resourcefile << endl;
    	return -1;
    };


	pugi::xpath_node_set pattlist; 

	vector <string> cqpatts;
	vector<string>::iterator cait;
	string sep;

	// see which pattributes to export
		cqpatts.push_back("form");
		cqpatts.push_back("id");
	pattlist = xmlsettings.select_nodes("//cqp/pattributes/item/@key");
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it)
	{
		pugi::xpath_node node = *it;
		
		// get the attribute to export
		cqpatts.push_back(node.attribute().value());
		
	};

	// Now we need to read the inheritance tree
	map < string, string > inherit;
	inherit["form"] = "pform"; // form ALWAYS inherits from pform
	pattlist = xmlsettings.select_nodes("//xmlfile//pattributes//item");
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it)
	{
		pugi::xpath_node xmlfnode = *it;		
		string patt = xmlfnode.node().attribute("key").value();
		if (xmlfnode.node().attribute("inherit")) { 
			inherit[patt] = xmlfnode.node().attribute("inherit").value();
		};
	};


    
    // Now - read the file 

    pugi::xml_document doc;
    if (!doc.load_file(filename.c_str())) {
        cout << "  Failed to load XML file " << filename << endl;
    	return -1;
    };

	pugi::xpath_node resnode;

    // Print the header
    
    // Always start with the fileid, ideally from the name after xmlfiles/
      unsigned found1 = filename.find("xmlfiles/"); 
      unsigned found2 = filename.find(".xml"); 
      string fileid;
      if ( found1+13 < filename.length() && found2 ) { 	
      	fileid = filename.substr(found1+9,(found2-found1)-9);
      } else { fileid = doc.select_node("//text/@id").attribute().value(); };
	cout << "<text id=\"" << fileid << "\"";  
	
    string xqp;
	pattlist = xmlsettings.select_nodes("//cqp//sattributes//item");
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it)
	{
		pugi::xpath_node xmlfnode = *it;		
		string patt = xmlfnode.node().attribute("key").value();
		if ( patt != "" ) {
			cout << " " << patt << "=";
			if ( xmlfnode.node().attribute("type").value() != "range" ) {
				cout << '"';
			};
			// lookup the value
			xqp = xmlfnode.node().attribute("xpath").value();
			if ( xqp != "" ) {
				resnode = doc.select_node(xqp.c_str());
				if ( resnode.attribute() ) {
					cout << resnode.attribute().value();
				} else {
					cout << resnode.node().child_value();
				};
			};
			if ( xmlfnode.node().attribute("type").value() != "range" ) {
				cout << '"';
			};
		};
	};
    cout << ">\n";
    
    // Go through the toks
    
	pugi::xpath_node_set toks = doc.select_nodes("//tok");

	string fld;
	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xpath_node node = *it;
		
		sep = "";
	    for( cait=cqpatts.begin() ; cait < cqpatts.end(); cait++ ) {
			fld = *cait;
			
			// lookup XPath queries on the first token only
			if ( fld.substr(0,1) == "/" && getsetting(fld) == "" ) {
				resnode = doc.select_node(fld.c_str());
				if ( resnode.attribute() ) {
					settings[fld] = resnode.attribute().value();
 				} else {
					settings[fld] = resnode.node().child_value();
				};
			};
			
			if ( fld.substr(0,1) == "/"  ) {
				cout << sep << settings[fld];
			} else {
				string getfld = fld;

				// go up the tree if necessary
				while ( !node.node().attribute(getfld.c_str()) && inherit[getfld] != "" ) {
					getfld = inherit[getfld];
				};

				if ( getfld == "pform" ) {
					cout << sep << node.node().child_value();
				} else {
					cout << sep << node.node().attribute(getfld.c_str()).value();
				};
			};
			sep = "\t";
			
		};
		cout << "\n";

			
	}

    cout << "</text>\n";

}
