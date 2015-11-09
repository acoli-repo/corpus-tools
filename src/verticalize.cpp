#include <boost/algorithm/string.hpp>
#include <boost/tokenizer.hpp>
#include <boost/foreach.hpp>
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

bool debug = false;

map <string, string> settings;
string getsetting ( string key );

// Variables in which we store the preferences
pugi::xpath_node_set pattlist; 
vector <string> cqpatts;
pugi::xml_document xmlsettings;
map < string, string > inherit;


string getsetting ( string key ) {
	if ( settings.find(key) == settings.end() ) {
		return "";
	} else {
		return settings[key];
	};
};

int treatnode ( pugi::xpath_node node ) {
	string sep = "";
	vector<string>::iterator cait;
	string fld;

	// Check if the form is not --
	string chk = node.node().attribute("form").value();
	if ( chk == "--" ) { 
		if ( debug ) { cout << "Skipping - empty string" << endl; };
		return -1;
	};

	for( cait=cqpatts.begin() ; cait < cqpatts.end(); cait++ ) {
		fld = *cait;
		
		if ( fld.substr(0,1) == "/"  ) {
			// This is an xpath query 
			cout << sep << settings[fld];
		} else {
			string getfld = fld;
			string fldval = "";
			
			// go up the tree if necessary
			while ( !node.node().attribute(getfld.c_str()) && inherit[getfld] != "" ) {
				getfld = inherit[getfld];
			};

			if ( getfld == "pform" ) {
				fldval = node.node().child_value();
			} else {
				fldval = node.node().attribute(getfld.c_str()).value();
			};

			if ( fldval == "<" ) { fldval = "&#60;"; }; // < is interpreted as an XML tag by cwb-encode

			cout << sep << fldval;

		};
		sep = "\t";
		
	};
	cout << "\n";
};

int treatfile ( string filename ) {

	if ( debug ) { cout << "Treating: " << filename << endl; };
	if ( filename.find(".xml") == -1 ) { return 0; }; // We can only treat XML files and assume those always end on .xml

    // Now - read the file 
	string sep;

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

	vector<string>::iterator cait;
	string fld;

	// Read the s-features for the whole doc
	for( cait=cqpatts.begin() ; cait < cqpatts.end(); cait++ ) {
		fld = *cait;
		
		// lookup XPath queries to add s-attributes
		// store the result in settings
		if ( fld.substr(0,1) == "/" && getsetting(fld) == "" ) {
			resnode = doc.select_node(fld.c_str());
			if ( resnode.attribute() ) {
				settings[fld] = resnode.attribute().value();
			} else {
				settings[fld] = resnode.node().child_value();
			};
		};
	};
	    
    // Go through the toks
	pugi::xpath_node_set toks = doc.select_nodes("//tok");

	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xpath_node node = *it;

		// unless told otherwise, your dtoks over toks		
		if ( node.node().children("dtok").end() != node.node().children("dtok").begin() ) {
			for (pugi::xml_node dnode = node.node().child("dtok"); dnode; dnode = dnode.next_sibling("dtok"))
			{
				treatnode(dnode);
			}
		} else {	
			treatnode(node);
		};
					
	}

    cout << "</text>\n";
};


int treatdir (string dirname) {
    struct dirent *entry;
    DIR *dp;
 
 	// We need kill any *.xml at the end, since we are treating folders, not a glob
	string patt = "*";
	if ( dirname.find(patt) !=std::string::npos ) {
		if ( debug ) { 
			cout << "  Cleanup: removing everything after * in " << dirname << endl;
		};
		dirname = dirname.substr(0,dirname.find(patt));
   	};
   	
	const char *path = dirname.c_str();


    dp = opendir(path);
    if (dp == NULL) {
    	if ( debug ) {
    		cout << "  Failed to open " << path << endl;
    	};
        return -1;
    }

   struct stat st;
   while ((entry = readdir(dp))) {
		string ffname = dirname + '/' + string(entry->d_name);
		lstat(ffname.c_str(), &st);
		if(S_ISDIR(st.st_mode)) {
		   if ( entry->d_name[0] != '.' ) { 
		   	  treatdir(ffname); 
		   };
		} else if(S_ISLNK(st.st_mode)) {
		  treatdir(ffname); // We assume links always to be links to directories
		} else {
		   treatfile(ffname);
		};
    };

    closedir(dp);
    return 0;
}

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

	// Read the settings file to see what we need to export
	string folder;
	if ( getsetting("folder") != "" ) { 
		folder = settings["folder"];
	} else if ( 1==2 )  { 
		// Not needed
		cout << "No folder indicated";
		return -1;	
	} else {
		// If nothing else, use the current folder
		folder = '.';
	};

	if ( getsetting("debug") == "1" ) { 
		debug = true; 
        cout << "-- Debugging mode " << endl;
	};
	
	string resourcefile = folder + "/Resources/settings.xml";
    
    if (!xmlsettings.load_file(resourcefile.c_str())) {
        cout << "  Failed to load settings file " << resourcefile << endl;
    	return -1;
    } else if ( debug ) {
        cout << "  Using settings from file " << resourcefile << endl;    	
    };

	

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
		if ( debug ) {
			cout << "  Exporting: " << node.attribute().value() << endl;    	
		};
	};

	// Now we need to read the inheritance tree
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
	
	string dofolders = xmlsettings.select_node("//cqp/@searchfolder").attribute().value();
	if ( getsetting("filename") != "" ) { 
		treatfile ( settings["filename"] );   
	} else if ( dofolders != "" ) {
		char_separator<char> sep(" ");
    	tokenizer< char_separator<char> > tokens(dofolders, sep);
		BOOST_FOREACH (const string& fldr, tokens) {
			if ( debug ) {
				cout << "  Verticalizing files from: " << folder + "/xmlfiles/" + fldr << endl;    	
			};
			treatdir ( folder + "/xmlfiles/" + fldr );
		}
	} else {
		treatdir ( folder + "/xmlfiles" ); 
	};
	
}
