#include <boost/algorithm/string.hpp>
#include <boost/tokenizer.hpp>
#include <boost/foreach.hpp>
#include <boost/filesystem.hpp>
#include "pugixml.hpp"
#include <iostream>
#include <sstream>  
#include <stdio.h>
#include <fstream>
#include <map>
#include <vector>
#include <dirent.h>
#include <sys/stat.h>
#include <arpa/inet.h>

using namespace std;
using namespace boost;

int debug = 0;
bool test = false;
bool verbose = false;

char tokxpath [50];
pugi::xml_document trainlog;
pugi::xml_node tagsettings;
pugi::xml_node trainstats;
list<string> formTags;

    pugi::xml_document doc;

string tagfld;
string tagpos;
string lemmafld;
string filename;
string corpusfolder;

int tokcnt = 0;

list<string> tagHist;
   
map<string, map<string, int> > lexitems; // .lexicon ids
map<string, map<string, ofstream*> > streams; // ascii output files
map<string, map<string, FILE*> > files; // real output files
map<string,int > lexidx; // max lexitems id
map<string,int > lexpos; // pos in the lexicon file

map<string,pugi::xml_document*> externals; // external XML files 

// Variables in which we store the preferences
pugi::xpath_node_set pattlist; 
vector <string> cqpatts;
pugi::xml_document xmlsettings;
map < string, string > inherit;

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


string calcform ( pugi::xml_node node, string fld ) {
	string getfld = fld;
	while ( !node.attribute(getfld.c_str()) && inherit[getfld] != "" ) {
		getfld = inherit[getfld];
	};
	if ( getfld == "pform" ) {
		return node.child_value();
	} else {
		return node.attribute(getfld.c_str()).value();
	};
};

// Write CWB network style
void write_network_number ( int towrite, FILE *stream ) {
	int i = htonl(towrite);
	fwrite(&i, 4, 1, stream);
};

// Write a range to .rng
void write_range ( int pos1, int pos2, string tagname ) {
	if ( pos2 < pos1 ) { return; }; // We can never have negative ranges
		
	write_network_number(pos1, files[tagname]["rng"]);
	write_network_number(pos2, files[tagname]["rng"]);
};

// Write a range to .rng, .avs, .avx
void write_range_value ( int pos1, int pos2, string tagname, string attname, string formval ) {
	if ( pos2 < pos1 ) { return; }; // We can never have negative ranges
	
	string formkey = tagname + "_" + attname;
	
	write_network_number(pos1, files[formkey]["rng"]);
	write_network_number(pos2, files[formkey]["rng"]);

	if ( lexitems[formkey].size() == 0 ) { lexpos[formkey] = 0; lexidx[formkey] = 0;  }; // Initialize values 
	
	// CWB does not like empty value - convert to underscore
	if ( formval == "" ) { formval = "_"; };

	if ( debug > 2 ) { cout << "Range: " << filename << " - " << formkey << " " << pos1 << "-" << pos2 << "  = " << formval << endl; };
	if ( lexitems[formkey].find(formval) == lexitems[formkey].end() ) {
		// new value		
		int thispos = lexpos[formkey];
		lexitems[formkey][formval] = thispos;
		if ( debug > 4 ) { cout << "New AVS value for " << formval << " = " << lexitems[formkey][formval] << " - " << thispos << endl; };
		*streams[formkey]["avs"] << formval << '\0'; 	streams[formkey]["avs"]->flush();
		lexpos[formkey] += formval.length() + 1;
	};
	write_network_number(lexidx[formkey], files[formkey]["avx"]);
	write_network_number(lexitems[formkey][formval], files[formkey]["avx"]);
	lexidx[formkey]++; 
	
};

void treatnode ( pugi::xpath_node node ) {
	string sep = "";
	vector<string>::iterator cait;
	pugi::xml_node lexitem;
	pugi::xml_node tok;
	
	if ( debug > 1 ) node.node().print(std::cout);

	if ( !strcmp( node.node().attribute("form").value(), "--" ) ) { 
		if ( debug > 1 ) { cout << "Skipping - empty string: " << node.node().attribute("id").value() << endl; };
		return;
	};

	// We have a valid token - handle it
	tokcnt++;

	// Write the .lexicon, .lexicon.idx and .corpus files
	string formkey; string formval; pugi::xpath_node xres;
    for ( pugi::xml_node formfld = xmlsettings.first_child().child("cqp").child("pattributes").child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
		string xpath = formfld.attribute("xpath").value();
		formkey = formfld.attribute("key").value(); 
		if ( xpath != "" ) {
			if ( debug > 4 ) { cout << "Calculating XPath value for node: " << xpath << endl; };
			 xres = node.node().select_single_node(xpath.c_str());
			if ( debug > 4 ) { xres.node().print(cout); };
			if ( xres.attribute() ) {	
				formval = xres.attribute().value();
			} else {
				formval = xres.node().child_value();
			};
		} else {
			if ( formkey == "word" ) { // we NEED a word in CQP
				formval = calcform(node.node(), "form");
			} else {
				formval = calcform(node.node(), formkey);
			};
		};
			
		if ( debug > 4 ) { cout << "Value: " << formkey << " = " << formval << endl; };
		if ( !lexitems[formkey][formval] ) {
			// new word		
			if ( debug > 4 ) { cout << formval << ":" << formkey << " - new value" << endl; };
			lexitems[formkey][formval] = lexidx[formkey];
			*streams[formkey]["lexicon"] << formval << '\0'; streams[formkey]["lexicon"]->flush();
			write_network_number(lexpos[formkey], files[formkey]["idx"]);
			lexidx[formkey]++; lexpos[formkey] += formval.length() + 1;
		};
		write_network_number(lexitems[formkey][formval], files[formkey]["corpus"]);


	};		

	// Write the word.xidx.rng
	int xmlpos1 = node.node().offset_debug()-1;
	std::ostringstream oss;
	node.node().print(oss);
	std::string xmltxt = oss.str();	
	int xmlpos2 = xmlpos1 + xmltxt.length(); 
	write_network_number(xmlpos1, files["xidx"]["rng"]);
	write_network_number(xmlpos2, files["xidx"]["rng"]);

};

// Determine the main pos
string getmainpos ( pugi::xml_node tok ) { 
	string pos = tok.attribute(tagpos.c_str()).value();
	return pos.substr(0,1);
};

void treatfile ( string filename ) {

	if ( filename.find(".xml") == -1 ) { 
        if ( debug > 0 ) { cout << "  Skipping non-XML file: " << filename << endl; };
		return; 
	}; // We can only treat XML files and assume those always end on .xml

    // Now - read the file 
	string sep;

    if (!doc.load_file(filename.c_str())) {
        cout << "  Failed to load XML file " << filename << endl;
    	return;
    };
    
    string fileid = filename.substr(filename.find_last_of("/")+1,filename.find_last_of(".")-filename.find_last_of("/")-1	);

	if ( debug > 0 ) { cout << "  - Treating: " << filename << " (" << fileid << ")" << endl; };

	pugi::xpath_node resnode;

	if ( tagsettings.attribute("restriction") != NULL 
			&& doc.select_single_node(tagsettings.attribute("restriction").value()) == NULL ) {
		if ( debug ) cout << "- XML " << filename << " not matching " << tagsettings.attribute("restriction") .value() << endl;
		return;
	};

	char tokxpath [50];
	if ( tagsettings.attribute("tokxpath") != NULL ) { strcpy(tokxpath, tagsettings.attribute("tokxpath").value()); } 
		else { strcpy(tokxpath, "//tok"); };
	if ( debug > 3 ) cout << "- treating all: " << tokxpath << endl;
	    
    // Go through the toks
	pugi::xpath_node_set toks = doc.select_nodes(tokxpath);
	map<string, int> id_pos;

	int pos1 = tokcnt;
	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xpath_node node = *it;
		id_pos[it->node().attribute("id").value()] = tokcnt;

		treatnode(node);
				
	}
	int pos2 = tokcnt-1;

	string idname;
	// idname = doc.select_single_node("//text/@id").attribute().value();
	idname = filename;
	
	// Add the default attributes for <text>
	if ( debug > 0 ) {
		cout << "<text> " << filename << " ranging from " << pos1 << " to " << pos2 << endl; 
	};

	write_range (pos1, pos2, "text" );
	write_range_value (pos1, pos2, "text", "id", idname);


	// add the sattributes for all levels
	string formkey; string formval; 
	string rel_tokxpath = tokxpath;
		rel_tokxpath = "." + rel_tokxpath;
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("sattributes").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
		if ( tagname == "text" ) {
			// This is the <text> level
			if ( !(pos2>pos1) ) { continue; }; // This will crash on texts without any tokens inside; do not add to CQP for now (but they should be added as indexes)
			for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
				formkey = formfld.attribute("key").value(); 
				if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
				formval = ""; // empty values
				string external = formfld.attribute("external").value();
				string xpath = formfld.attribute("xpath").value();
				if ( xpath != "" ) {
					pugi::xpath_node xres;
					if ( external != "" ) {
						if ( debug > 2 ) { cout << "External XML lookup: " << external << endl; };
						string tmp = doc.select_single_node(external.c_str()).attribute().value();
						if ( debug > 3 ) { cout << " -- lookup value: " << tmp << endl; };
						  vector <string> exval; 
						  // Initialize srings
						  split( exval, tmp, is_any_of( "#" ) );
						string exfile = exval[0];
						if ( exfile.substr(exfile.length()-4) == ".xml" && externals[exfile] == NULL ) { 
							exfile = "Resources/" + exfile;
							if ( verbose ) { cout << "Loading external XML file: " << exfile << " < " << tmp << endl; };
							externals[exval[0]] = new pugi::xml_document();
							externals[exval[0]]->load_file(exfile.c_str());
						};
						if ( exfile.substr(exfile.length()-4) != ".xml" && debug > 0 ) {
							cout << "Invalid external lookup: " << tmp << endl; 
						};
						if ( externals[exval[0]] != NULL ) { 
							if ( exval.size() > 1 ) {
								string idlookup = "//*[@id=\""+exval[1]+"\"]";
								if ( debug > 1 ) { cout << "ID lookup: " << idlookup << endl; };
								pugi::xml_node exnode;
								try {
									exnode = externals[exval[0]]->select_single_node(idlookup.c_str()).node();
								} catch(pugi::xpath_exception& e) { if ( debug > 4 ) { cout << "XPath error" << endl; };  };
								if ( debug > 2 ) { exnode.print(cout); };
								if ( exnode ) {
									xres = exnode.select_single_node(xpath.c_str()); 
								};
							} else {
								try {
									xres = externals[exval[0]]->first_child().select_single_node(xpath.c_str()); 
								} catch(pugi::xpath_exception& e) { if ( debug > 4 ) { cout << "XPath error" << endl; };  };
							};
						};
					} else {
						 xres = doc.select_single_node(xpath.c_str());
					};
					if ( xres.attribute() ) {	
						formval = xres.attribute().value();
					} else {
						formval = xres.node().child_value();
					};
				};

				write_range_value (pos1, pos2, "text", formkey, formval);
			};	
		} else {
			if ( debug > 2 ) { cout << "Looking for " << tagname << endl; };
			// Add non-text level attributes
			string xpath = "//text//" + tagname;
			// Loop through the actual items
			pugi::xpath_node_set elmres = doc.select_nodes(xpath.c_str());
			for (pugi::xpath_node_set::const_iterator it = elmres.begin(); it != elmres.end(); ++it) {
				pugi::xpath_node_set rel_toks = it->node().select_nodes(rel_tokxpath.c_str());
				if ( rel_toks.empty() ) { continue; };
				string toka = rel_toks[0].node().attribute("id").value();
				string tokb = rel_toks[rel_toks.size()-1].node().attribute("id").value();
				int posa = id_pos[toka]; // first "token" in the range
				int posb = id_pos[tokb]; // last "token" in the range
				if ( debug > 2 ) { cout << " Found a range " << tagname << " " << it->node().attribute("id").value() << " from " << toka << " (" << posa << ") to " << tokb << " (" << posb << ")" << endl; };

				write_range(posa, posb, tagname );
				for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
					formkey = formfld.attribute("key").value(); 
					if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
					formval = "";
					xpath = formfld.attribute("xpath").value();
					if ( xpath != "" ) {
						pugi::xpath_node xres;
						xres = it->node().select_single_node(xpath.c_str());
						if ( xres.attribute() ) {	
							formval = xres.attribute().value();
						} else {
							formval = xres.node().child_value();
						};
					} else { 
						formval = it->node().attribute(formfld.attribute("key").value()).value();
					};
					
					
					// write the actual data
					write_range_value (posa, posb, tagname, formkey, formval);

					// Write the XXX.xidx.rng
					int xmlpos1 = it->node().offset_debug()-1;
// 					std::ostringstream oss;
// 					it->node().print(oss); // This is the interpreted XML, which is too long... get beginning of next node instead
// 					std::string xmltxt = oss.str();	
// 					int xmlpos2 = xmlpos1 + xmltxt.length(); 
					int xmlpos2 = it->node().select_single_node("./following::*").node().offset_debug()-1;
					write_network_number(xmlpos1, files[tagname + "_xidx"]["rng"]);
					write_network_number(xmlpos2, files[tagname + "_xidx"]["rng"]);
				};
			};	

		};
	};

	// add the stand-off annotations
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("annotations").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
		if ( debug > 2 ) { cout << " - Looking for stand-off: " << tagname << endl; };
		// Loop through the actual items
		string xpath = "//file[@id=\""+fileid+"\"]/segment";
		pugi::xpath_node_set elmres = externals[taglevel.attribute("filename").value()]->select_nodes(xpath.c_str());
		for (pugi::xpath_node_set::const_iterator it = elmres.begin(); it != elmres.end(); ++it) {
			string wlist = it->node().attribute("tokens").value();
			string toka = wlist.substr(0,wlist.find(","));
			string tokb = wlist.substr(wlist.find_last_of(",")+1);
			if ( toka == "" || tokb == "" ) { continue; };
			int posa = id_pos[toka]; // first "token" in the range
			int posb = id_pos[tokb]; // last "token" in the range
			if ( debug > 2 ) { cout << " Found a range " << tagname << " " << it->node().attribute("id").value() << " from " << toka << " (" << posa << ") to " << tokb << " (" << posb << ")" << endl; };

			write_range(posa, posb, tagname);
			for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
				formkey = formfld.attribute("key").value(); 
				if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
				formval = "";
				formval = it->node().attribute(formfld.attribute("key").value()).value();

				// write the actual data
				write_range_value(posa, posb, tagname, formkey, formval);
			};
		};	
	};
};


int treatdir (string dirname) {
    struct dirent *entry;
    DIR *dp;

	if ( verbose ) {
		cout << "- Treating " << dirname << endl; 
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
	tagsettings = trainlog.first_child().append_child("settings");	
	trainstats = trainlog.first_child().append_child("stats");	

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
				tagsettings.append_attribute(akey.c_str()) = "1";
			} else {
				string akey = argm.substr(2,spacepos-2);
				string aval = argm.substr(spacepos+1);
				tagsettings.append_attribute(akey.c_str()) = aval.c_str();
			};
		};		
	};

	// Some things we want as accessible variables
	if ( tagsettings.attribute("debug") != NULL ) { debug = atoi(tagsettings.attribute("debug").value()); };
	if ( tagsettings.attribute("test") != NULL ) { test = true; verbose = true; };
	if ( tagsettings.attribute("verbose") != NULL ) { verbose = true; };

	// Output help information when so asked and quit
	if ( tagsettings.attribute("help") != NULL ) { 
		cout << "Usage:  tt-cwb-encode [options]" << endl;
		cout << "" << endl;
		cout << "Reads a collection of tokenized XML files, and generates CWB binary format corpus files, which can be converted into a full CWB corpus with cwb-makeall. Settings for the conversion are typcially read from an XML style settings file, which by default is called Resources/settings.xml. More information about the structure of the settings file can be found on: http://teitok.corpuswiki.org" << endl;
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
	if ( tagsettings.attribute("settings") != NULL ) { 
		settingsfile = tagsettings.attribute("settings").value();
	} else {
		folder = ".";
		settingsfile = "./Resources/settings.xml";
	};
    if ( xmlsettings.load_file(settingsfile.c_str())) {
    	if ( verbose ) { cout << "- Using settings from " << settingsfile << endl;   }; 	
    };
	
	pugi::xml_node parameters = xmlsettings.select_single_node("//cqp").node();
	if ( parameters == NULL ) {
		cout << "- No parameters for CQP found" << endl;
		return -1;
	};

	// Place all neotag parameter settings from the settings.xml into the tagsettings
	for (pugi::xml_attribute_iterator it = parameters.attributes_begin(); it != parameters.attributes_end(); ++it)
	{
		if ( tagsettings.attribute((*it).name()) == NULL ) { 
			tagsettings.append_attribute((*it).name()) =  (*it).value();
		};
	};
	// Also take settings from the //neotag root ([item]/../..)
	for (pugi::xml_attribute_iterator it = parameters.parent().parent().attributes_begin(); it != parameters.parent().parent().attributes_end(); ++it)
	{
		if ( tagsettings.attribute((*it).name()) == NULL ) { 
			tagsettings.append_attribute((*it).name()) =  (*it).value();
		};
	};
	
	// Determine some default settings
	if ( tagsettings.attribute("corpusfolder") != NULL ) { corpusfolder = tagsettings.attribute("corpusfolder").value(); } 
		else { corpusfolder = "cqp/"; };

	// Check whether the corpusfolder exists, or create it, or fail
    boost::filesystem::path dir(corpusfolder.c_str());
    if(boost::filesystem::create_directory(dir))
    {
        if ( verbose ) { cout << "Directory Created: "<< corpusfolder << endl; };
    }	

	if ( xmlsettings.select_nodes("//neotag/pattributes/item[@key=\"word\"]").empty() ) { 
		pugi::xml_node watt = xmlsettings.first_child().child("cqp").child("pattributes").append_child("item");
		watt.append_attribute("key") = "word";
	};
	if ( xmlsettings.select_nodes("//neotag/pattributes/item[@key=\"id\"]").empty() ) { 
		pugi::xml_node watt = xmlsettings.first_child().child("cqp").child("pattributes").append_child("item");
		watt.append_attribute("key") = "id";
	};
	if ( debug > 2) { xmlsettings.first_child().child("cqp").child("pattributes").print(cout); };
	string sep;

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

	// Write the registry file
	string corpusname = xmlsettings.select_single_node("//cqp/@corpus").attribute().value();
	string corpuslong = xmlsettings.select_single_node("//title/@display").attribute().value();
	corpusname = strtolower(corpusname);
	string registryfile = "/usr/local/share/cwb/registry/" + corpusname;
	if ( verbose ) { cout << "Writing registry data to: " << registryfile << endl; };
	ofstream registry;
	registry.open(registryfile.c_str());
	registry << "## Registry file for the corpus " << corpusname << endl;
	registry << "## Created from XML file by TEITOK" << endl;
	registry << "## Generated by tt-cwb-encode" << endl << endl;

	registry << "NAME \"" << corpuslong << "\"" << endl;
	registry << "ID " << corpusname << endl;
	registry << "HOME " << realpath("cqp", NULL) << endl;
	registry << "INFO " << realpath("cqp", NULL) << "/.info" << endl;
	
	// Go through the pattributes
	registry << endl << "## Positional attributes on <tok>" << endl;
	string formkey; string formval; string longname;
    for ( pugi::xml_node formfld = xmlsettings.first_child().child("cqp").child("pattributes").child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
		formkey = formfld.attribute("key").value(); 
		longname = formfld.attribute("display").value();
		if ( longname == "" ) {
			string tmp = "//pattributes//item[@key=\""+formkey+"\"]/@display";
			longname = xmlsettings.select_single_node(tmp.c_str()).attribute().value();
		};
		registry << "ATTRIBUTE " << formkey << "  # " << longname << endl;
		
		if ( debug > 0 ) { cout << "Creating files for: " << formkey << endl; };
		
		// Open the files
		filename = corpusfolder+formkey+".lexicon";
		streams[formkey]["lexicon"] = new ofstream(filename.c_str());
		filename = corpusfolder+formkey+".lexicon.idx";
		files[formkey]["idx"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+formkey+".corpus";
		files[formkey]["corpus"] = fopen(filename.c_str(), "wb"); 
	};		

	
	// go through the sattributes on all levels
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("sattributes").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
			if ( debug > 0  ) { cout << "Creating files for: " << tagname << endl; };
		registry << endl << "## Structural attributes on <" << tagname << ">" << endl;
		registry << "STRUCTURE " << tagname << endl;
		filename = corpusfolder+tagname+".rng";
		files[tagname]["rng"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+tagname+"_xidx.rng";
		files[tagname+"_xidx"]["rng"] = fopen(filename.c_str(), "wb"); 
		for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
			formkey = formfld.attribute("key").value(); 
			if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
			longname = formfld.attribute("long").value();
			if ( longname == "" ) { longname = formfld.attribute("display").value(); };
			registry << "STRUCTURE " << tagname << "_" << formkey << "  # " << longname << endl;

			if ( debug > 0  ) { cout << "Creating attribute files for: " << formkey << endl; };
			filename = corpusfolder+tagname+"_"+formkey+".avs";
			streams[tagname+"_"+formkey]["avs"] = new ofstream(filename.c_str());
			filename = corpusfolder+tagname+"_"+formkey+".avx";
			files[tagname+"_"+formkey]["avx"] = fopen(filename.c_str(), "wb"); 
			filename = corpusfolder+tagname+"_"+formkey+".rng";
			files[tagname+"_"+formkey]["rng"] = fopen(filename.c_str(), "wb"); 
		};
	};
	// There always should be a text_id
	if ( streams["text_id"]["avs"] == NULL ) {
		if ( debug > 4 ) { cout << "Creating text_id" << endl; };
		registry << "STRUCTURE text_id" << endl;
		filename = corpusfolder+"text_id.avs";
		streams["text_id"]["avs"] = new ofstream(filename.c_str());
		filename = corpusfolder+"text_id.avx";
		files["text_id"]["avx"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+"xidx.rng";
		files["xidx"]["rng"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+"text_id.rng";
		files["text_id"]["rng"] = fopen(filename.c_str(), "wb"); 
	};
	
	// go through the stand-off annotations
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("annotations").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
		string filename = taglevel.attribute("filename").value();
		if ( debug > 4 ) { cout << "Stand-off annotation: " << filename << endl; };

		// open the external XML file
		string fullfilename = "Annotations/"+filename;
			cout << "Loading: " << fullfilename << endl; 
		
		externals[filename] = new pugi::xml_document();
		try {
			externals[filename]->load_file(fullfilename.c_str());
		} catch(pugi::xpath_exception& e) { if ( debug > 4 ) { cout << "XPath error" << endl; };  };
		if ( externals[filename] == NULL ) {
			cout << "Failed to load: " << fullfilename << endl; 
			taglevel.parent().remove_child(taglevel); // Remove this node since we cannot read the stand-off annotation
			continue; 
		};
			cout << "Loaded: " << fullfilename << endl; 
		
		if ( verbose ) { cout << "Loading external annotations XML: " << filename << endl; };
		registry << endl << "## Stand-off annotations of type " << tagname << endl;
		registry << "STRUCTURE " << tagname << endl;
		filename = corpusfolder+tagname+".rng";
		files[tagname]["rng"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+tagname+"_xidx.rng";
		files[tagname+"_xidx"]["rng"] = fopen(filename.c_str(), "wb"); 
		for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
			formkey = formfld.attribute("key").value(); 
			if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
			longname = formfld.attribute("long").value();
			if ( longname == "" ) { longname = formfld.attribute("display").value(); };
			registry << "STRUCTURE " << tagname + "_" + formkey << "  # " << longname << endl;

			if ( debug > 0  ) { cout << "Creating attribute files for: " << formkey << endl; };
			filename = corpusfolder+tagname+"_" + formkey+".avs";
			streams[tagname+"_" + formkey]["avs"] = new ofstream(filename.c_str());
			filename = corpusfolder+tagname+"_" + formkey+".avx";
			files[tagname+"_" + formkey]["avx"] = fopen(filename.c_str(), "wb"); 
			filename = corpusfolder+tagname+"_" + formkey+".rng";
			files[tagname+"_" + formkey]["rng"] = fopen(filename.c_str(), "wb"); 
		};

	};
		
	string dofolders = tagsettings.attribute("searchfolder").value();
	if ( dofolders != "" ) {
		if ( verbose ) cout << "- Training folder(s): " << dofolders << endl;
		char_separator<char> sep(" ");
    	tokenizer< char_separator<char> > tokens(dofolders, sep);
		BOOST_FOREACH (const string& fldr, tokens) {
			if ( debug ) {
				cout << "  - Analyzing files from: " << fldr << endl;    	
			};
			treatdir ( fldr );
		}
	} else {
		if ( verbose ) cout << "- Training folder: ./xmlfiles" << endl;
		treatdir ( "xmlfiles" ); 
	};

	if ( verbose ) { cout << "- Calculating additional data" << endl; };


	if ( verbose ) cout << "- " << tokcnt << " tokens in CQP corpus" << endl; 
	trainstats.append_attribute("tokens") = tokcnt;
	
	if ( tagsettings.attribute("log") != NULL ) {
		cout << "- Saving log to: " << tagsettings.attribute("log").value() << endl;
		trainlog.save_file(tagsettings.attribute("log").value());
	};

}
