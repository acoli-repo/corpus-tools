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

string extension = ".xml";
int debug = 0;
bool group = false;
bool test = false;
bool verbose = false;

std::ostream* myout;

char tokxpath [50];
pugi::xml_document trainlog;
pugi::xml_node clsettings;
pugi::xml_node cqpstats;
vector<string> formTags;

pugi::xml_document doc;
pugi::xml_document xmlsettings;

string xprest;
string xpquery;
string cluster = "";
map<string,int > clustVals;
string filepat = "";
int perpage = 100;
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

	if ( perpage > 0 && sofar > perpage-1 ) { return; };

	pugi::xml_document doc;

	if ( debug > 2 ) {	cout << "-- Trying: " << filename << endl;  };

	if ( filename.find(extension) == -1 ) { 
        if ( debug > 0 ) { cout << "  Skipping non-XML file: " << filename << endl; };
		return; 
	}; // We can only treat XML files and assume those always end on .xml

	string fileid = filename;
	fileid = fileid.substr(fileid.find("/") + 1);

    // Now - read the file 
	string sep;

    if (!doc.load_file(filename.c_str(), pugi::parse_ws_pcdata)) {
        cout << "  Failed to load XML file " << filename << endl;
    	return;
    };

	// Run the XPath restriction to see if this file is appropriate		
	pugi::xpath_node_set xres;
	if ( filepat != "" ) {
		string regex = ".*"+filepat+".*";
		if ( !preg_match(filename, regex) ) {
			if ( verbose ) {
				cout << "-- Rejecting, filename restriction not met: " << regex << endl; 
			};
			return;
		};
	}
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
		
	if ( debug > 1 ) {	cout << "-- Eligible file: " << filename << endl;  };

	// Run the XPath query to return the results		
	if ( xpquery != "" ) {
		string xpp = xpquery;
        if ( debug > 0 ) { cout << "  Running query: " << xpp << endl; };
		
		xres = doc.select_nodes(xpp.c_str()); 
		if ( group ) {
			if ( xres.size() > 0 ) {
				sofar += xres.size();
				*myout << "<doc name=\"" << filename << "\" rescnt=\"" << xres.size() << "\""; 
				*myout << "/>" << endl;
			};
		} else if ( cluster != "" ) { 
			for (pugi::xpath_node_set::const_iterator it = xres.begin(); it != xres.end(); ++it) {
				pugi::xpath_node node = *it;
				string attval = node.node().attribute(cluster.c_str()).value();
				if ( debug ) { cout << cluster << " = " << attval << endl; };
				clustVals[attval]++;
			};
		} else {
			for (pugi::xpath_node_set::const_iterator it = xres.begin(); it != xres.end(); ++it) {
				pugi::xpath_node node = *it;
				sofar = sofar + 1;
				if ( debug > 0 ) { cout << "  Result nr: " << sofar << " / " << perpage << endl; };
				node.node().append_attribute("resnr") = to_string(sofar).c_str();
				node.node().append_attribute("fileid") = fileid.c_str();
				if ( sofar > start ) { 
					node.node().print(*myout);
				};
				if ( perpage > 0 && sofar > perpage-1 ) {
					break;
				}
			};
		};
	};

};

std::string ReplaceAll(std::string str, const std::string& from, const std::string& to) {
    size_t start_pos = 0;
    while((start_pos = str.find(from, start_pos)) != std::string::npos) {
        str.replace(start_pos, from.length(), to);
        start_pos += to.length(); // Handles case where 'to' is a substring of 'from'
    }
    return str;
}

string attprotect ( string att ) {
	att = ReplaceAll(att, "&", "&amp;");
	att = ReplaceAll(att, "'", "&#039;");
	att = ReplaceAll(att, "<", "&lt;");
	att = ReplaceAll(att, ">", "&gt;");
	return att;
}

int treatdir (string dirname) {
    struct dirent *entry;
    DIR *dp;

	if ( perpage > 0 && sofar > perpage-1 ) { return 0; };

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
	if ( clsettings.attribute("group") != NULL ) { group = true; perpage = 0; };
	if ( clsettings.attribute("test") != NULL ) { test = true; verbose = true; };
	if ( clsettings.attribute("verbose") != NULL ) { verbose = true; };
	if ( clsettings.attribute("extension") != NULL ) { 
		extension = clsettings.attribute("extension").value(); 
		if ( extension.substr(0,1) != "." ) { extension = "." + extension; };
    	if ( verbose ) { cout << "- Using extension " << extension << endl;   }; 	
	};

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
		cout << "  --group	 	  count results per file" << endl;
		cout << "  --cluster=[s]  count results by attribute" << endl;
		cout << "  --max=[i]	  max number of results	" << endl;
		cout << "  --filename=[s] while file to search in" << endl;
		cout << "  --folder=[s]	  while folder to search in" << endl;
		cout << "  --xprest=[s]   only run on files matching this" << endl;
		cout << "  --xpquery=[s]  the XPath query to run" << endl;
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
	if ( clsettings.attribute("filename") != NULL ) { filepat = clsettings.attribute("filename").value(); }
	if ( clsettings.attribute("xpquery") != NULL ) { xpquery = clsettings.attribute("xpquery").value(); }
	if ( clsettings.attribute("cluster") != NULL ) { cluster = clsettings.attribute("cluster").value(); }
	if ( clsettings.attribute("max") != NULL ) { perpage = atoi(clsettings.attribute("max").value()); };
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

	if ( clsettings.attribute("header") == NULL && strstr(xpquery.c_str(), "text") == NULL && extension == ".xml" ) {
		// Unless specifically asked to, only look in the //text
		string base = "";
		if ( xpquery.substr(0,1) != "/" ) {
			base = "//";
		};
		xpquery = "//text"+base+xpquery;
	};

	if ( clsettings.attribute("outfile") != NULL ) { 
		string outfile = clsettings.attribute("outfile").value(); 
		if ( verbose ) { cout << "Writing output to " << outfile << endl; };
		myout = new std::ofstream(outfile.c_str());
	} else {
		myout = &std::cout;
	}
	
	if ( verbose ) { 
		cout << "Query: " << xpquery << endl;
	};

	string moreout =  " query='" + attprotect(xpquery) + "'";
	if ( cluster != "" ) { moreout += " cluster='" + attprotect(cluster) + "'"; };

	sofar = 0;
	*myout << "<results" << moreout << " start='" << start << "' max='" << perpage << "'>" << endl;

	// This does not listen to the command line at the moment, should be reverted back to clsettings
	string dofolders;
	string sep;
	if ( clsettings.attribute("folder") != NULL ) { dofolders = clsettings.attribute("folder").value(); }
	else {
		dofolders = xmlsettings.select_node("//cqp/@searchfolder").attribute().value();
	};
	string docfile;
	if ( clsettings.attribute("files") != NULL ) { docfile = clsettings.attribute("files").value(); }


	if ( docfile != "" ) {
		if ( verbose ) {
			cout << "  - Analyzing files from list: " << docfile << endl;    	
		};
		ifstream dfile(docfile);
		if (dfile.is_open()) {
			string tfile;
			while (getline(dfile, tfile)) {
				if ( debug > 1 ) {
					cout << "  - Analyzing file: " << tfile << endl;    	
				};
				treatfile(tfile);
			}
			dfile.close();
		}
	} else if ( dofolders != "" ) {
		if ( verbose ) cout << "- Indexing folder(s): " << dofolders << endl;
		vector<string> tokens = split(dofolders, sep); 
		for( vector<string>::iterator it2 = tokens.begin(); it2 != tokens.end(); it2++ ) {
			string fldr = *it2;	
			if ( verbose ) {
				cout << "  - Analyzing files from: " << fldr << endl;    	
			};
			treatdir ( fldr );
		}
	} else if ( filepat != "" ) {
		if ( verbose ) {
			cout << "  - Analyzing file: " << filepat << endl;    	
		};
		treatfile(filepat);
	} else {	
		cout << "Error: no file or folder specified" << endl;    	
		return -1; 
	};

	*myout << "</results>" << endl;
	if ( verbose ) cout << "Total number of matches found: " << sofar << endl;

}
