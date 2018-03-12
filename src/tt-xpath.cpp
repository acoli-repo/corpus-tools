#include <iostream>
#include <sstream>  
#include <stdio.h>
#include <fstream>
#include <map>
#include <vector>
#include <string.h>
#include <dirent.h>
#include <sys/stat.h>
#include <arpa/inet.h>
#include "pugixml.hpp"
#include "functions.hpp"

using namespace std;

int debug = 0;
bool test = false;
bool verbose = false;

char tokxpath [50];
pugi::xml_document trainlog;
pugi::xml_node clsettings;
pugi::xml_node cqpstats;
vector<string> formTags;

pugi::xml_document doc;
pugi::xml_document xmlsettings;

string xprest;
string xpquery;
int perpage;
int start;
int sofar;

// String to Lowercase
// should be made UTF 
string strtolower ( string str ) {
  string lowercase = "";  int i=0;
  char c;
  while (str[i])
  {
    c=str[i];
    lowercase.append(1, tolower(c));
    i++;
  }
  return lowercase;
};
string strtoupper ( string str ) {
  string uppercase = "";  int i=0;
  char c;
  while (str[i])
  {
    c=str[i];
    uppercase.append(1, toupper(c));
    i++;
  }
  return uppercase;
};

bool file_exists (const std::string& name) {
    ifstream f(name.c_str());
    return f.good();
}

void treatfile ( string filename ) {

	if ( sofar > perpage-1 ) { return; };

	pugi::xml_document doc;

	if ( filename.find(".xml") == -1 ) { 
        if ( debug > 0 ) { cout << "  Skipping non-XML file: " << filename << endl; };
		return; 
	}; // We can only treat XML files and assume those always end on .xml

	string fileid = filename;
	fileid = fileid.substr(fileid.find("/") + 1);

    // Now - read the file 
	string sep;

    if (!doc.load_file(filename.c_str())) {
        cout << "  Failed to load XML file " << filename << endl;
    	return;
    };

	// Run the XPath restriction to see if this file is appropriate		
	pugi::xpath_node_set xres;
	if ( xprest != "" ) {
		string xpp = xprest;
		
		xres = doc.select_nodes(xpp.c_str());
		if ( xres.size() == 0 ) {
			if ( verbose ) {
				cout << "-- Rejecting, XPath restriction not met: " << xpp << endl; 
			};
			return;
		};
	};

	if ( verbose ) {	cout << "-- Eligible file: " << filename << endl;  };
	
	// Run the XPath query to return the results		
	if ( xpquery != "" ) {
		string xpp = xpquery;
        if ( debug > 0 ) { cout << "  Running query: " << xpp << endl; };
		
		xres = doc.select_nodes(xpp.c_str()); 
		for (pugi::xpath_node_set::const_iterator it = xres.begin(); it != xres.end(); ++it) {
			pugi::xpath_node node = *it;
			sofar = sofar + 1;
	        if ( debug > 0 ) { cout << "  Result nr: " << sofar << " / " << perpage << endl; };
	        node.node().append_attribute("resnr") = to_string(sofar).c_str();
	        node.node().append_attribute("fileid") = fileid.c_str();
			if ( sofar > start ) { node.node().print(cout); };
			if ( sofar > perpage-1 ) {
				break;
			};
		}
	};
	

};


int treatdir (string dirname) {
    struct dirent *entry;
    DIR *dp;

	if ( sofar > perpage-1 ) { return 0; };

	if ( verbose ) {
		cout << "- Treating folder " << dirname << endl; 
	};
 
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
    		cout << "  Failed to open dir: " << path << endl;
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
};

int main(int argc, char *argv[])
{

	trainlog.append_child("xmltrain");
	clsettings = trainlog.first_child().append_child("settings");	
	cqpstats = trainlog.first_child().append_child("stats");	

	time_t beginT = clock(); time_t tm = time(0);
	string tmp = ctime(&tm);
	trainlog.first_child().append_attribute("starttime") = tmp.substr(0,tmp.length()-1).c_str();	

	// Read in all the command-line arguments
	for ( int i=1; i< argc; ++i ) {
		string argm = argv[i];
		
		if ( argm.substr(0,2) == "--" ) {
			int spacepos = argm.find("=");
			
			if ( spacepos == -1 ) {
				string akey = argm.substr(2);
				clsettings.append_attribute(akey.c_str()) = "1";
			} else {
				string akey = argm.substr(2,spacepos-2);
				string aval = argm.substr(spacepos+1);
				clsettings.append_attribute(akey.c_str()) = aval.c_str();
			};
		};		
	};

	// Some things we want as accessible variables
	verbose = false; debug = false; test = false;
	if ( clsettings.attribute("debug") != NULL ) { debug = atoi(clsettings.attribute("debug").value()); };
	if ( clsettings.attribute("test") != NULL ) { test = true; verbose = true; };
	if ( clsettings.attribute("verbose") != NULL ) { verbose = true; };

	if ( clsettings.attribute("version") != NULL ) { 
		cout << "tt-xpath version 1.0" << endl;
		return -1; 
	};

	// Output help information when so asked and quit
	if ( clsettings.attribute("help") != NULL ) { 
		cout << "Usage:  tt-xpath [options]" << endl;
		cout << "" << endl;
		cout << "Returns the result of an xpath query on a collection of XML files" << endl;
		cout << "" << endl;
		cout << "Options:" << endl;
		cout << "  --debug=[n]    debug level" << endl;
		cout << "  --verbose	  verbose output" << endl;
		cout << "  --settings=[s] name of the settings file" << endl;
		cout << "  --log=[s]	  write log to file [s]" << endl;
		return -1; 
	};
	
	// Read the settings.xml file where appropriate - by default from ./Resources/settings.xml
	string settingsfile;
	string folder;
	if ( clsettings.attribute("settings") != NULL ) { 
		settingsfile = clsettings.attribute("settings").value();
	} else {
		folder = ".";
		settingsfile = "./Resources/settings.xml";
	};
    if ( xmlsettings.load_file(settingsfile.c_str())) {
    	if ( verbose ) { cout << "- Using settings from " << settingsfile << endl;   }; 	
    };

	xpquery = ""; xprest = "";
	if ( clsettings.attribute("xprest") != NULL ) { xprest = clsettings.attribute("xprest").value(); }
	if ( clsettings.attribute("xpquery") != NULL ) { xpquery = clsettings.attribute("xpquery").value(); }
	if ( clsettings.attribute("max") != NULL ) { perpage = atoi(clsettings.attribute("max").value()); } else { perpage = 100; };
	if ( clsettings.attribute("start") != NULL ) { start = atoi(clsettings.attribute("start").value()); } else { start = 0; };


	if ( xpquery == "" ) {
		string tmp = argv[argc-1];
		if ( tmp.substr(0,2) != "--" ) {
			xpquery = tmp;
		};
	};

	if ( xpquery == "" ) {
		cout << "Usage: tt-xpath [options] query" << endl;
		return -1;
	};

	if ( verbose ) { 
		cout << "Query: " << xpquery << endl;
	};
	if ( strstr(xpquery.c_str(), "text") == NULL ) {
		string base = "";
		if ( xpquery.substr(0,1) != "/" ) {
			base = "//";
		};
		xpquery = "//text"+base+xpquery;
	};
	
	if ( verbose ) { 
		cout << "Query: " << xpquery << endl;
	};

	sofar = 0;
	cout << "<results start='" << start << "' max='" << perpage << "'>" << endl;

	// This does not listen to the command line at the moment, should be reverted back to clsettings
	string dofolders;
	string sep;
	if ( clsettings.attribute("folder") != NULL ) { dofolders = clsettings.attribute("folder").value(); }
	else {
		dofolders = xmlsettings.select_node("//cqp/@searchfolder").attribute().value();
	};
	if ( dofolders != "" ) {
		if ( verbose ) cout << "- Indexing folder(s): " << dofolders << endl;
		vector<string> tokens = split(dofolders, sep); 
		for( vector<string>::iterator it2 = tokens.begin(); it2 != tokens.end(); it2++ ) {
			string fldr = *it2;	
			if ( debug ) {
				cout << "  - Analyzing files from: " << fldr << endl;    	
			};
			treatdir ( fldr );
		}
	} else {
		if ( verbose ) cout << "- Default training folder: ./xmlfiles" << endl;
		treatdir ( "xmlfiles" ); 
	};

	cout << "</results>" << endl;

}
