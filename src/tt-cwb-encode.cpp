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

int debug = 0;
bool test = false;
bool verbose = false;

char tokxpath [50];
pugi::xml_document trainlog;
pugi::xml_node tagsettings;
pugi::xml_node trainstats;
list<string> formTags;

string tagfld;
string tagpos;
string lemmafld;
string filename;

int tokcnt = 0;

list<string> tagHist;
   
map<string, map<string, int> > lexitems; // .lexicon ids
map<string, map<string, ofstream> > streams; // ascii output files
map<string, map<string, FILE*> > files; // real output files
map<string,int > lexidx; // max lexitems id
map<string,int > lexpos; // pos in the lexicon file

map<string,pugi::xml_document> externals; // external XML files 

// Variables in which we store the preferences
pugi::xpath_node_set pattlist; 
vector <string> cqpatts;
pugi::xml_document xmlsettings;
map < string, string > inherit;


// Write CWB network style
void write_network_number ( int towrite, FILE *stream ) {
	int i = htonl(towrite);
	fwrite(&i, 4, 1, stream);
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

	string formkey; string formval;
    for ( pugi::xml_node formfld = xmlsettings.first_child().child("cqp").child("pattributes").child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
		formkey = formfld.attribute("key").value(); 
		if ( formkey == "word" ) { // we NEED a word in CQP
			formval = calcform(node.node(), "form");
		} else {
			formval = calcform(node.node(), formkey);
		};
		if ( !streams[formkey]["lexicon"].is_open() ) { 
			if ( verbose ) { cout << "Creating files for: " << formkey << endl; };
			streams[formkey]["lexicon"].open("corpus/"+formkey+".lexicon");
			filename = "corpus/"+formkey+".lexicon.idx";
			files[formkey]["idx"] = fopen(filename.c_str(), "wb"); 
			filename = "corpus/"+formkey+".corpus";
			files[formkey]["corpus"] = fopen(filename.c_str(), "wb"); 
		};
	
		// if ( lexitems[formkey].find(formval) == lexitems[formkey].end() ) {
		if ( !lexitems[formkey][formval] ) {
			// new word		
			lexitems[formkey][formval] = lexidx[formkey];
			streams[formkey]["lexicon"] << formval << '\0';
			write_network_number(lexpos[formkey], files[formkey]["idx"]);
			lexidx[formkey]++; lexpos[formkey] += formval.length() + 1;
		};
		write_network_number(lexitems[formkey][formval], files[formkey]["corpus"]);
	};		

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

    pugi::xml_document doc;
    if (!doc.load_file(filename.c_str())) {
        cout << "  Failed to load XML file " << filename << endl;
    	return;
    };

	if ( debug ) { cout << "Treating: " << filename << endl; };

	pugi::xpath_node resnode;

	if ( tagsettings.attribute("restriction") != NULL 
			&& doc.select_single_node(tagsettings.attribute("restriction").value()) == NULL ) {
		if ( debug ) cout << "- XML " << filename << " not matching " << tagsettings.attribute("restriction") .value() << endl;
		return;
	};

	char tokxpath [50];
	if ( tagsettings.attribute("tokxpath") != NULL ) { strcpy(tokxpath, tagsettings.attribute("tokxpath").value()); } 
		else { strcpy(tokxpath, "//tok"); };
	    
    // Go through the toks
	pugi::xpath_node_set toks = doc.select_nodes(tokxpath);

	int pos1 = tokcnt;
	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xpath_node node = *it;

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
	if ( !streams["text_id"]["avs"].is_open() ) { 
		streams["text_id"]["avs"].open("corpus/text_id.avs");
		filename = "corpus/text_id.avx";
		files["text_id"]["avx"] = fopen(filename.c_str(), "wb"); 
		filename = "corpus/text_id.rng";
		files["text_id"]["rng"] = fopen(filename.c_str(), "wb"); 
		filename = "corpus/text.rng";
		files["text_"]["rng"] = fopen(filename.c_str(), "wb"); 
	};	
	if ( pos2 > pos1 ) {
		write_network_number(pos1, files["text_"]["rng"]);
		write_network_number(pos2, files["text_"]["rng"]);
		write_network_number(pos1, files["text_id"]["rng"]);
		write_network_number(pos2, files["text_id"]["rng"]);
		write_network_number(lexidx["text_id"], files["text_id"]["avx"]);
		write_network_number(lexpos["text_id"], files["text_id"]["avx"]);
	};
	lexidx["text_id"]++; lexpos["text_id"] += idname.length() + 1;
	streams["text_id"]["avs"] << idname << '\0'; 

	// add the sattributes for <text>
	string formkey; string formval;
    for ( pugi::xml_node formfld = xmlsettings.first_child().child("cqp").child("sattributes").child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
		formkey = formfld.attribute("key").value(); 
		if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
		formkey = "text_" + formkey;
		formval = "";
		string external = formfld.attribute("external").value();
		string xpath = formfld.attribute("xpath").value();
		if ( xpath != "" ) {
			pugi::xpath_node xres;
			if ( external != "" ) {
				if ( debug > 2 ) { cout << "External XML lookup: " << external << endl; };
				string tmp = doc.select_single_node(external.c_str()).attribute().value();
				  vector <string> exval;
				  split( exval, tmp, is_any_of( "#" ) );
				string exfile = exval[0];
				if ( exfile.substr(exfile.length()-4) == ".xml" && !externals[exfile].first_child() ) { 
					exfile = "Resources/" + exfile;
					if ( verbose ) { cout << "Loading external XML file: " << exfile << " < " << tmp << endl; };
					externals[exval[0]].load_file(exfile.c_str());
				};
				if ( exfile.substr(exfile.length()-4) != ".xml" && verbose ) {
					cout << "Invalid external lookup: " << tmp << endl; 
				};
				if ( externals[exval[0]].first_child() ) { 
					if ( exval[1] != "" ) {
						string idlookup = "//*[@id=\""+exval[1]+"\"]";
						if ( debug > 1 ) { cout << "ID lookup: " << idlookup << endl; };
						pugi::xml_node exnode = externals[exval[0]].select_single_node(idlookup.c_str()).node();
						if ( debug > 2 ) { exnode.print(cout); };
						if ( exnode ) {
							xres = exnode.select_single_node(xpath.c_str()); 
						};
					} else {
						xres = externals[exval[0]].select_single_node(xpath.c_str()); 
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
		if ( debug > 0 ) { cout << filename << " - " << formkey << " (" << xpath << ") = " << formval << endl; };

		if ( !streams[formkey]["avs"].is_open() ) { 
			if ( verbose ) { cout << "Creating files for: " << formkey << endl; };
			streams[formkey]["avs"].open("corpus/"+formkey+".avs");
			filename = "corpus/"+formkey+".avx";
			files[formkey]["avx"] = fopen(filename.c_str(), "wb"); 
			filename = "corpus/"+formkey+".rng";
			files[formkey]["rng"] = fopen(filename.c_str(), "wb"); 
		};
	
		// if ( lexitems[formkey].find(formval) == lexitems[formkey].end() ) {
		if ( !lexitems[formkey][formval] ) {
			// new word		
			lexitems[formkey][formval] = lexidx[formkey];
			streams[formkey]["avs"] << formval << '\0'; 
		};
		if ( pos2 > pos1 ) {
			write_network_number(pos1, files[formkey]["rng"]);
			write_network_number(pos2, files[formkey]["rng"]);
			write_network_number(lexidx[formkey], files[formkey]["avx"]);
			write_network_number(lexpos[formkey], files[formkey]["avx"]);
			lexpos[formkey] += formval.length() + 1;
			lexidx[formkey]++; 
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
	
	// Determine which field to tag from and on
	tmp = "";
	if ( tagsettings.attribute("tagform") != NULL ) { tagfld = tagsettings.attribute("tagform").value(); } 
		else { tagfld = "form"; };
	if ( tagsettings.attribute("tagpos") != NULL ) { tagpos = tagsettings.attribute("tagpos").value(); } 
		else { tagpos = "pos"; };
	if ( tagsettings.attribute("lemmatize") != NULL ) { lemmafld = tagsettings.attribute("lemmatize").value(); } 
		else { lemmafld = "form"; };
	if ( tagsettings.attribute("formtags") != NULL ) { 
		tmp= tagsettings.attribute("formtags").value(); 
	} else { 
		tmp = "lemma,"+tagpos; // By default, tag for lemma and pos
	};
	split(formTags, tmp, is_any_of(",")); 

	if ( xmlsettings.select_nodes("//neotag/pattributes/item[@key=\"word\"]").empty() ) { 
		pugi::xml_node watt = xmlsettings.first_child().child("cqp").child("pattributes").append_child("item");
		watt.append_attribute("key") = "word";
	};
	if ( xmlsettings.select_nodes("//neotag/pattributes/item[@key=\"id\"]").empty() ) { 
		pugi::xml_node watt = xmlsettings.first_child().child("cqp").child("pattributes").append_child("item");
		watt.append_attribute("key") = "id";
	};
	xmlsettings.first_child().child("cqp").child("pattributes").print(cout);
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
